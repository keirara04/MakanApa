<?php

namespace App\Console\Commands;

use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pending-disk photos don't disappear just because they're inaccessible — this is the retention
 * policy for them. `approved`/`pending`/`changes_requested` submissions' photos are never
 * touched. Run daily via the scheduler.
 */
#[Signature('restaurant-photos:prune')]
#[Description('Delete pending-disk photos for cancelled/rejected/abandoned-draft submissions')]
class PruneRestaurantSubmissionPhotos extends Command
{
    public function handle(): int
    {
        $deleted = 0;

        $deleted += $this->pruneForSubmissions(
            RestaurantSubmission::where('status', 'cancelled')->pluck('id')
        );

        $deleted += $this->pruneForSubmissions(
            RestaurantSubmission::where('status', 'rejected')->where('reviewed_at', '<=', now()->subDays(30))->pluck('id')
        );

        $deleted += $this->pruneForSubmissions(
            RestaurantSubmission::where('status', 'draft')->where('updated_at', '<=', now()->subDays(7))->pluck('id')
        );

        $this->info("Pruned {$deleted} pending photo(s).");

        return self::SUCCESS;
    }

    private function pruneForSubmissions($submissionIds): int
    {
        $photos = RestaurantPhoto::whereIn('restaurant_submission_id', $submissionIds)
            ->whereNull('restaurant_id') // never touch anything already promoted
            ->get();

        foreach ($photos as $photo) {
            Storage::disk($photo->disk)->delete($photo->path);
            $photo->delete();
        }

        return $photos->count();
    }
}
