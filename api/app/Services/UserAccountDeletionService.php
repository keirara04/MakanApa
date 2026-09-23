<?php

namespace App\Services;

use App\Models\AccountDeletion;
use App\Models\CommunityPost;
use App\Models\RestaurantPhoto;
use App\Models\User;
use App\Services\Auth\AppleTokenExchangeService;
use App\Services\Community\CommunityPostService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Owns every side effect of self-service account deletion, so AuthController::destroy() stays
 * orchestration (validate the request, call this, respond) rather than the permanent home for
 * deletion policy.
 */
class UserAccountDeletionService
{
    public function __construct(private AppleTokenExchangeService $appleExchange, private CommunityPostService $communityPosts) {}

    public function delete(User $user): void
    {
        if ($user->apple_refresh_token) {
            $this->appleExchange->revoke($user->apple_refresh_token);
        }

        $this->deleteUploadedPhotos($user);

        // Deletion is instant and self-service — there's no admin-reviewed queue for this.
        // This is the only trace left afterward, so admins have some visibility (support,
        // abuse patterns, compliance) once the row itself is gone.
        AccountDeletion::create(['user_id' => $user->id, 'email' => $user->email]);

        $user->tokens()->delete();
        // forceDelete(), not delete() — the User model gained SoftDeletes for the admin panel's
        // reversible moderation delete, but self-service deletion is a genuine, permanent
        // removal (matches what the privacy policy promises). AccountDeletion above is the only
        // trace meant to survive this.
        // Community posts/reactions/reports go with the user via cascadeOnDelete (a personal post
        // is removed, not de-attributed — same reasoning as photos below). Replies they left on
        // other people's posts are cascaded away too, so those parents' reply_count is recounted.
        $repliedParentIds = CommunityPost::withTrashed()->where('user_id', $user->id)->whereNotNull('parent_id')->distinct()->pluck('parent_id');

        $user->forceDelete();

        CommunityPost::whereIn('id', $repliedParentIds)->get()->each(fn (CommunityPost $parent) => $this->communityPosts->recountReplies($parent));
    }

    /**
     * Apple's account-deletion guidance requires deleting user-generated content shared with
     * others (photos), not just de-attributing it — unlike Decision/RestaurantSave/
     * RestaurantSubmission, which anonymize via nullOnDelete because that data becomes
     * canonical restaurant/recommendation data rather than a personal post.
     */
    private function deleteUploadedPhotos(User $user): void
    {
        RestaurantPhoto::where('uploaded_by', $user->id)->get()->each(function (RestaurantPhoto $photo) {
            // A false return means the object deletion did not happen — deleting the row anyway
            // would leave an orphaned, still-public file with nothing pointing at it. Object
            // storage deletes aren't part of the DB transaction, so this can't be made perfectly
            // atomic; failing loudly here (aborting the whole deletion) is the accepted v1
            // tradeoff over building an outbox/retry system.
            if (! Storage::disk($photo->disk)->delete($photo->path)) {
                throw new RuntimeException("Failed to delete uploaded photo file: {$photo->disk}:{$photo->path}");
            }

            $photo->delete();
        });
    }
}
