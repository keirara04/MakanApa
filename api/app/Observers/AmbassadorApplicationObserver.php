<?php

namespace App\Observers;

use App\Models\AmbassadorApplication;
use App\Services\AdminAlertService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Rings the admin bell for each new ambassador application. */
class AmbassadorApplicationObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly AdminAlertService $alerts) {}

    public function created(AmbassadorApplication $application): void
    {
        if ($application->status === 'pending') {
            $this->alerts->ambassadorApplicationCreated($application);
        }
    }
}
