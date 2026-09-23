<?php

namespace App\Console\Commands;

use App\Models\RestaurantSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Drafts never submitted (user opened a report/claim, maybe uploaded a photo, closed the app)
 * become `cancelled` so zombie drafts don't pile up or block the one-open-report rule forever.
 * Their photos are then deleted by restaurant-photos:prune, which already handles cancelled.
 */
#[Signature('community:prune-drafts')]
#[Description('Cancel community submission drafts untouched for longer than halal.draft_ttl_days')]
class PruneAbandonedSubmissionDrafts extends Command
{
    public function handle(): int
    {
        $count = RestaurantSubmission::where('status', 'draft')
            ->where('updated_at', '<=', now()->subDays((int) config('halal.draft_ttl_days', 7)))
            ->update(['status' => 'cancelled']);

        $this->info("Cancelled {$count} abandoned draft(s).");

        return self::SUCCESS;
    }
}
