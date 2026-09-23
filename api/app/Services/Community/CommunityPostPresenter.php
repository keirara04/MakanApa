<?php

namespace App\Services\Community;

use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\CommunityReaction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Shapes posts for the API in a fixed number of queries per page (never per post): one for
 * reaction tallies, one for the viewer's own reactions, and — for top-level posts — one
 * window-function query for every post's reply preview at once.
 */
class CommunityPostPresenter
{
    /**
     * @param  Collection<int, CommunityPost>  $posts
     * @return array<int, array<string, mixed>>
     */
    public function presentMany(Collection $posts, User $viewer, bool $withReplyPreview): array
    {
        if ($posts->isEmpty()) {
            return [];
        }

        $previews = $withReplyPreview ? $this->replyPreviews($posts->pluck('id')->all(), $viewer) : collect();
        $all = new EloquentCollection($posts->concat($previews->flatten(1))->all());
        $all->loadMissing(['user', 'restaurant']);

        [$tallies, $mine] = $this->reactionData($all->pluck('id')->all(), $viewer);

        return $posts->map(function (CommunityPost $post) use ($tallies, $mine, $viewer, $previews, $withReplyPreview) {
            $data = $this->present($post, $viewer, $tallies, $mine);

            if ($withReplyPreview) {
                $data['replies'] = ($previews->get($post->id) ?? collect())
                    ->map(fn (CommunityPost $reply) => $this->present($reply, $viewer, $tallies, $mine))
                    ->values()->all();
            }

            return $data;
        })->values()->all();
    }

    public function presentOne(CommunityPost $post, User $viewer): array
    {
        return $this->presentMany(collect([$post]), $viewer, withReplyPreview: ! $post->isReply())[0];
    }

    private function present(CommunityPost $post, User $viewer, array $tallies, array $mine): array
    {
        $author = $post->user;
        $authorGone = ! $author || $author->trashed();

        return [
            'id' => $post->id,
            'parentId' => $post->parent_id,
            'body' => $post->body,
            'createdAt' => $post->created_at->toIso8601String(),
            'author' => [
                'id' => $authorGone ? null : $author->id,
                'name' => $authorGone ? 'Deleted user' : ($author->name ?: 'MakanApa user'),
                'avatarKey' => $authorGone ? null : $author->avatar_key,
            ],
            'isMine' => $post->user_id === $viewer->id,
            'restaurant' => $post->restaurant && $post->restaurant->is_active ? [
                'id' => $post->restaurant->id,
                'name' => $post->restaurant->name,
                'foodCategory' => $post->restaurant->food_category,
            ] : null,
            'reactionCount' => $post->reaction_count,
            'reactions' => $tallies[$post->id] ?? (object) [],
            'myReaction' => $mine[$post->id] ?? null,
            'replyCount' => $post->reply_count,
        ];
    }

    /** @return array{0: array<int, array<string, int>>, 1: array<int, string>} */
    private function reactionData(array $postIds, User $viewer): array
    {
        $tallies = [];
        CommunityPostReaction::query()
            ->whereIn('community_post_id', $postIds)
            ->selectRaw('community_post_id, type, count(*) as total')
            ->groupBy('community_post_id', 'type')
            ->get()
            ->each(function ($row) use (&$tallies) {
                $type = $row->type instanceof CommunityReaction ? $row->type->value : $row->type;
                $tallies[$row->community_post_id][$type] = (int) $row->total;
            });

        $mine = CommunityPostReaction::query()
            ->whereIn('community_post_id', $postIds)
            ->where('user_id', $viewer->id)
            ->get(['community_post_id', 'type'])
            ->mapWithKeys(fn ($r) => [$r->community_post_id => $r->type->value])
            ->all();

        return [$tallies, $mine];
    }

    /**
     * The N newest readable replies per parent, returned oldest-first so they read top-down
     * like the full thread does.
     *
     * @return Collection<int, Collection<int, CommunityPost>> keyed by parent id
     */
    private function replyPreviews(array $parentIds, User $viewer): Collection
    {
        $limit = (int) Config::get('moderation.community_posts.reply_preview_count', 2);
        $hidden = UserBlock::hiddenUserIdsFor($viewer);

        $ranked = CommunityPost::query()
            ->visible()
            ->whereIn('parent_id', $parentIds)
            ->when($hidden, fn ($q) => $q->whereNotIn('user_id', $hidden))
            ->selectRaw('community_posts.*, row_number() over (partition by parent_id order by id desc) as preview_rank');

        return CommunityPost::query()
            ->fromSub($ranked, 'community_posts')
            ->where('preview_rank', '<=', $limit)
            ->orderBy('id')
            ->get()
            ->groupBy('parent_id');
    }
}
