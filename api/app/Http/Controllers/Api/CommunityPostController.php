<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunityPostRequest;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Community\CommunityPostPresenter;
use App\Services\Community\CommunityPostService;
use App\Support\CommunityReaction;
use App\Support\CommunityReportReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * "What KU is saying" — short posts scoped to the caller's university/area community, with one
 * level of replies. The community always comes from the caller's own affiliation, never from a
 * request parameter, so there's no way to read another community's board. Public (unaffiliated)
 * users get an empty, read-only response rather than an error, so the iOS section can render a
 * "join a community" prompt from the same payload.
 */
class CommunityPostController extends Controller
{
    public function __construct(
        private readonly CommunityPostService $posts,
        private readonly CommunityPostPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurantId' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $cannotPostReason = $this->posts->postingBlockedReason($user);

        if (! $this->isAffiliated($user)) {
            return response()->json(['posts' => [], 'nextCursor' => null, 'canPost' => false, 'cannotPostReason' => $cannotPostReason]);
        }

        $page = CommunityPost::query()
            ->visible()
            ->topLevel()
            ->inCommunityOf($user)
            ->notBlockedFor($user)
            ->when(isset($data['restaurantId']), fn ($q) => $q->where('restaurant_id', $data['restaurantId']))
            ->orderByDesc('id')
            ->cursorPaginate($data['limit'] ?? Config::get('moderation.community_posts.page_size', 20));

        return response()->json([
            'posts' => $this->presenter->presentMany(collect($page->items()), $user, withReplyPreview: true),
            'nextCursor' => $page->nextCursor()?->encode(),
            'canPost' => $cannotPostReason === null,
            'cannotPostReason' => $cannotPostReason,
        ]);
    }

    /** The full thread: the parent plus its replies, oldest first. */
    public function show(Request $request, CommunityPost $post): JsonResponse
    {
        $user = $request->user();
        $this->posts->assertReadable($post, $user);

        if ($post->isReply()) {
            // Deep links (e.g. a reaction notification) may point at a reply — serve its thread.
            $post = $post->parent;
            $this->posts->assertReadable($post, $user);
        }

        $page = $post->replies()
            ->visible()
            ->notBlockedFor($user)
            ->orderBy('id')
            ->cursorPaginate(Config::get('moderation.community_posts.page_size', 20));

        $parent = $this->presenter->presentMany(collect([$post]), $user, withReplyPreview: false)[0];

        return response()->json([
            'post' => $parent,
            'replies' => $this->presenter->presentMany(collect($page->items()), $user, withReplyPreview: false),
            'nextCursor' => $page->nextCursor()?->encode(),
            'canReply' => $this->posts->postingBlockedReason($user) === null,
        ]);
    }

    public function store(StoreCommunityPostRequest $request): JsonResponse
    {
        $data = $request->validated();

        $post = $this->posts->create(
            $request->user(),
            $data['body'],
            isset($data['restaurantId']) ? (int) $data['restaurantId'] : null,
            isset($data['parentId']) ? (int) $data['parentId'] : null,
        );

        return response()->json(['post' => $this->presenter->presentOne($post->fresh(), $request->user())], 201);
    }

    public function destroy(Request $request, CommunityPost $post): JsonResponse
    {
        $this->posts->delete($request->user(), $post);

        return response()->json(['deleted' => true]);
    }

    public function react(Request $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(CommunityReaction::class)],
        ]);

        $mine = $this->posts->toggleReaction($request->user(), $post, CommunityReaction::from($data['type']));
        $post->refresh();

        return response()->json([
            'myReaction' => $mine?->value,
            'reactionCount' => $post->reaction_count,
            'reactions' => $post->reactions()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->map(fn ($n) => (int) $n)->toArray() ?: (object) [],
        ]);
    }

    public function report(Request $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::enum(CommunityReportReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->posts->report($request->user(), $post, CommunityReportReason::from($data['reason']), $data['note'] ?? null);

        return response()->json(['reported' => true]);
    }

    public function blocks(Request $request): JsonResponse
    {
        $blocked = User::query()
            ->join('user_blocks', 'user_blocks.blocked_id', '=', 'users.id')
            ->where('user_blocks.blocker_id', $request->user()->id)
            ->orderByDesc('user_blocks.created_at')
            ->get(['users.id', 'users.name', 'users.avatar_key']);

        return response()->json([
            'users' => $blocked->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name ?: 'MakanApa user', 'avatarKey' => $u->avatar_key])->values(),
        ]);
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $this->posts->block($request->user(), $user);

        return response()->json(['blocked' => true]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $this->posts->unblock($request->user(), $user);

        return response()->json(['blocked' => false]);
    }

    private function isAffiliated(User $user): bool
    {
        return $user->universityId() !== null || $user->areaId() !== null;
    }
}
