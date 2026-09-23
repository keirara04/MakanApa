<?php

namespace App\Services\Community;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side state changes for community posts. Every change resolves the post's open reports
 * and writes an audit row in the same transaction (see AdminAuditLogger).
 *
 *  - hide:    reversible, e.g. while deciding.
 *  - restore: back to visible; open reports are resolved as "kept" (the admin judged it fine).
 *  - remove:  final takedown; open reports are resolved as "removed".
 */
class CommunityPostModerationService
{
    public function __construct(
        private readonly AdminAuditLogger $auditLogger,
        private readonly CommunityPostService $posts,
    ) {}

    public function hide(CommunityPost $post, User $admin, ?string $reason = null): void
    {
        $this->transition($post, $admin, CommunityPost::STATUS_HIDDEN, 'admin', 'community_post.hide', $reason, resolution: null);
    }

    public function restore(CommunityPost $post, User $admin): void
    {
        $this->transition($post, $admin, CommunityPost::STATUS_VISIBLE, null, 'community_post.restore', null, resolution: 'kept');
    }

    public function remove(CommunityPost $post, User $admin, ?string $reason = null): void
    {
        $this->transition($post, $admin, CommunityPost::STATUS_REMOVED, 'admin', 'community_post.remove', $reason, resolution: 'removed');
    }

    private function transition(CommunityPost $post, User $admin, string $status, ?string $hiddenReason, string $action, ?string $reason, ?string $resolution): void
    {
        DB::transaction(function () use ($post, $admin, $status, $hiddenReason, $action, $reason, $resolution) {
            $from = $post->status;

            if ($resolution !== null) {
                $post->reports()->whereNull('resolved_at')->update([
                    'resolved_at' => now(),
                    'resolved_by' => $admin->id,
                    'resolution' => $resolution,
                ]);
            }

            $post->update([
                'status' => $status,
                'hidden_reason' => $hiddenReason,
                'report_count' => $post->reports()->whereNull('resolved_at')->count(),
            ]);

            if ($post->parent) {
                $this->posts->recountReplies($post->parent);
            }

            $this->auditLogger->log($admin, $action, $post, $reason, ['from' => $from, 'to' => $status]);
        });
    }
}
