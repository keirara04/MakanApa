<?php

namespace App\Observers;

use App\Models\RestaurantSubmission;
use App\Services\AdminAlertService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Rings the admin bell whenever a submission (place, edit, photo, halal report, owner claim)
 * enters the review queue — covers every submit path at once. After commit, so a rolled-back
 * submit never alerts anyone.
 */
class RestaurantSubmissionObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly AdminAlertService $alerts) {}

    public function created(RestaurantSubmission $submission): void
    {
        if ($submission->status === 'pending') {
            $this->alerts->submissionPending($submission);
        }
    }

    // Not saved() + wasRecentlyCreated: that flag stays true on the instance for the rest of the
    // request, so every later save of a just-created draft would look like a new submission.
    public function updated(RestaurantSubmission $submission): void
    {
        if ($submission->status === 'pending' && $submission->wasChanged('status')) {
            $this->alerts->submissionPending($submission);
        }
    }
}
