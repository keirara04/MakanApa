<?php

namespace App\Services\Community;

use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Models\CommunityPostReport;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\CommunityPostReacted;
use App\Notifications\CommunityPostReplied;
use App\Support\CommunityContentFilter;
use App\Support\CommunityReaction;
use App\Support\CommunityReportReason;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * User-side writes for community posts. Posts publish immediately (no pre-review): the content
 * filter is the only gate, and reports auto-hide past a threshold for an admin to review in
 * Filament (CommunityPostModerationService). Counters on community_posts are always recomputed
 * from their source tables inside the same transaction, never incremented, so they can't drift.
 */
class CommunityPostService
{
    /** @return string|null why $user can't post right now, or null when they can */
    public function postingBlockedReason(User $user): ?string
    {
        if (! $user->isActive()) {
            return 'Your account is suspended.';
        }

        if ($user->universityId() === null && $user->areaId() === null) {
            return 'Join a university or area community to post.';
        }

        $minHours = (int) Config::get('moderation.community_posts.min_account_age_hours', 0);
        if (! $user->trusted_contributor && $minHours > 0 && $user->created_at?->gt(now()->subHours($minHours))) {
            return 'New accounts can post after their first day.';
        }

        return null;
    }

    public function create(User $user, string $body, ?int $restaurantId = null, ?int $parentId = null): CommunityPost
    {
        if ($reason = $this->postingBlockedReason($user)) {
            throw new HttpException(403, $reason);
        }

        $body = $this->cleanBody($body);
        if ($reason = CommunityContentFilter::rejectionReason($body)) {
            throw ValidationException::withMessages(['body' => $reason]);
        }

        $parent = null;
        if ($parentId !== null) {
            $parent = CommunityPost::find($parentId);
            // 404-shaped, not 422: from the replier's side a hidden/other-community/blocked
            // parent simply doesn't exist, and saying otherwise leaks that it does.
            if (! $parent || ! $this->isReadableBy($parent, $user)) {
                throw new NotFoundHttpException('That post is no longer available.');
            }
            if ($parent->isReply()) {
                throw ValidationException::withMessages(['parentId' => "You can't reply to a reply."]);
            }
            if ($restaurantId !== null) {
                throw ValidationException::withMessages(['restaurantId' => 'Replies can\'t tag a place.']);
            }
        }

        if ($restaurantId !== null && ! Restaurant::whereKey($restaurantId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['restaurantId' => 'That place is no longer available.']);
        }

        $post = DB::transaction(function () use ($user, $body, $restaurantId, $parent) {
            $post = CommunityPost::create([
                'user_id' => $user->id,
                'parent_id' => $parent?->id,
                // A reply takes its parent's community, not the replier's current one — they're
                // equal today (isReadableBy checked it), but the thread must never straddle two.
                'university_id' => $parent ? $parent->university_id : $user->universityId(),
                'area_id' => $parent ? $parent->area_id : ($user->universityId() === null ? $user->areaId() : null),
                'restaurant_id' => $restaurantId,
                'body' => $body,
                'status' => CommunityPost::STATUS_VISIBLE,
            ]);

            if ($parent) {
                $this->recountReplies($parent);
            }

            return $post;
        });

        if ($parent && $parent->user_id !== $user->id && $parent->user && ! UserBlock::existsBetween($parent->user_id, $user->id)) {
            $parent->user->notify(new CommunityPostReplied($parent, $post, $user));
        }

        return $post;
    }

    public function delete(User $user, CommunityPost $post): void
    {
        if ($post->user_id !== $user->id) {
            throw new HttpException(403, 'You can only delete your own posts.');
        }

        DB::transaction(function () use ($post) {
            $post->delete();

            if ($post->parent) {
                $this->recountReplies($post->parent);
            }
        });
    }

    /**
     * Same type again removes the reaction (a toggle); a different type switches it in place.
     *
     * @return CommunityReaction|null the caller's reaction after the toggle
     */
    public function toggleReaction(User $user, CommunityPost $post, CommunityReaction $type): ?CommunityReaction
    {
        $this->assertReadable($post, $user);

        [$result, $isNew] = DB::transaction(function () use ($user, $post, $type) {
            $existing = CommunityPostReaction::where('community_post_id', $post->id)->where('user_id', $user->id)->lockForUpdate()->first();

            if ($existing && $existing->type === $type) {
                $existing->delete();
                $result = [null, false];
            } elseif ($existing) {
                $existing->update(['type' => $type]);
                $result = [$type, false];
            } else {
                CommunityPostReaction::create(['community_post_id' => $post->id, 'user_id' => $user->id, 'type' => $type]);
                $result = [$type, true];
            }

            $post->update(['reaction_count' => $post->reactions()->count()]);

            return $result;
        });

        if ($isNew && $post->user_id !== $user->id && $post->user) {
            $post->user->notify(new CommunityPostReacted($post, $type, $user));
        }

        return $result;
    }

    public function report(User $user, CommunityPost $post, CommunityReportReason $reason, ?string $note = null): void
    {
        $this->assertReadable($post, $user);

        if ($post->user_id === $user->id) {
            throw ValidationException::withMessages(['post' => "You can't report your own post."]);
        }

        DB::transaction(function () use ($user, $post, $reason, $note) {
            CommunityPostReport::firstOrCreate(
                ['community_post_id' => $post->id, 'reporter_id' => $user->id],
                ['reason' => $reason, 'note' => $note !== null ? trim($note) : null],
            );

            $openReports = $post->reports()->whereNull('resolved_at')->count();
            $threshold = (int) Config::get('moderation.community_posts.report_hide_threshold', 3);

            $post->report_count = $openReports;
            if ($post->isVisible() && $openReports >= $threshold) {
                $post->status = CommunityPost::STATUS_HIDDEN;
                $post->hidden_reason = 'reports';
            }
            $post->save();

            if ($post->parent) {
                $this->recountReplies($post->parent);
            }
        });
    }

    public function block(User $blocker, User $target): void
    {
        if ($blocker->id === $target->id) {
            throw ValidationException::withMessages(['user' => "You can't block yourself."]);
        }

        UserBlock::firstOrCreate(['blocker_id' => $blocker->id, 'blocked_id' => $target->id]);
    }

    public function unblock(User $blocker, User $target): void
    {
        UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $target->id)->delete();
    }

    /**
     * Visible (and parent visible, for a reply), in $user's current community, and no block in
     * either direction between $user and the author.
     */
    public function isReadableBy(CommunityPost $post, User $user): bool
    {
        if (! $post->isVisible() || ! $post->belongsToCommunityOf($user)) {
            return false;
        }

        if ($post->isReply() && (! $post->parent || ! $post->parent->isVisible())) {
            return false;
        }

        return $post->user_id === $user->id || ! UserBlock::existsBetween($post->user_id, $user->id);
    }

    public function assertReadable(CommunityPost $post, User $user): void
    {
        if (! $this->isReadableBy($post, $user)) {
            throw new NotFoundHttpException('That post is no longer available.');
        }
    }

    /** Visible, non-deleted replies only — what readers can actually open. */
    public function recountReplies(CommunityPost $parent): void
    {
        $parent->update(['reply_count' => $parent->replies()->visible()->count()]);
    }

    private function cleanBody(string $body): string
    {
        // Collapse runs of blank lines — a 280-char post shouldn't be able to take up a screen.
        $body = preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", trim($body))) ?? '';
        $max = (int) Config::get('moderation.community_posts.max_length', 280);

        if ($body === '' || mb_strlen($body) > $max) {
            throw ValidationException::withMessages(['body' => "Posts must be 1–{$max} characters."]);
        }

        return $body;
    }
}
