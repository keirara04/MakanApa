<?php

namespace App\Listeners;

use App\Events\HalalVerificationRecorded;
use App\Notifications\HalalReportSuperseded;
use App\Services\Halal\ContributorCredibilityService;

/**
 * When a human decision replaces one that a contributor's report established AND changes the
 * status, that contributor is told — and it counts as "overturned" for internal credibility.
 * Same-status renewals (a newer cert confirming the same thing) are not an overturn.
 */
class NotifyHalalReportSuperseded
{
    public function __construct(private readonly ContributorCredibilityService $credibility) {}

    public function handle(HalalVerificationRecorded $event): void
    {
        $previous = $event->superseded;
        if ($previous === null || $previous->submission_id === null || $previous->status === $event->verification->status) {
            return;
        }

        $contributor = $previous->submission?->user;
        if ($contributor === null || $contributor->id === $event->verification->submission?->user_id) {
            return;
        }

        $this->credibility->record($contributor, 'overturned');
        $contributor->notify(new HalalReportSuperseded($event->verification, $event->verification->restaurant->name));
    }
}
