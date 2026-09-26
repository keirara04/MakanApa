<?php

namespace App\Observers;

use App\Models\CommunityRequest;
use App\Services\AdminAlertService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Rings the admin bell for each new "my university/area isn't listed" request. */
class CommunityRequestObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly AdminAlertService $alerts) {}

    public function created(CommunityRequest $request): void
    {
        if ($request->status === 'pending') {
            $this->alerts->communityRequestCreated($request);
        }
    }
}
