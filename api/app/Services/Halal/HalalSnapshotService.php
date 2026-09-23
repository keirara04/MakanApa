<?php

namespace App\Services\Halal;

use App\Models\Restaurant;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantSubmission;
use App\Support\Halal\HalalStatus;
use App\Support\Halal\HalalVerificationState;

/**
 * The only writer of restaurants.halal_*. Everything is derived from the verification ledger,
 * the active certificate and pending reports — so this is safe to re-run at any time
 * (halal:rebuild-snapshots) and converges to the same answer.
 */
class HalalSnapshotService
{
    public function __construct(private readonly HalalReviewStateResolver $reviewStateResolver) {}

    public function rebuild(Restaurant $restaurant): Restaurant
    {
        $active = RestaurantHalalVerification::where('restaurant_id', $restaurant->id)
            ->where('state', HalalVerificationState::Active)
            ->latest('effective_from')->latest('id')
            ->with('certificate')
            ->first();

        $latest = $active ?? RestaurantHalalVerification::where('restaurant_id', $restaurant->id)
            ->latest('effective_from')->latest('id')
            ->first();

        $certificate = $active?->certificate;
        $status = $active?->status ?? HalalStatus::Unknown;

        // A certified decision whose cert is no longer usable (expired/revoked/unverifiable)
        // must not keep presenting as certified, whatever state the ledger row is in.
        if ($status === HalalStatus::Certified && ($certificate === null || ! $certificate->isUsable())) {
            $status = HalalStatus::Unknown;
        }

        $pendingReports = RestaurantSubmission::where('restaurant_id', $restaurant->id)
            ->where('submission_type', 'halal_report')
            ->where('status', 'pending')
            ->get(['id', 'halal_claim', 'created_at']);

        $restaurant->forceFill([
            'halal_status' => $status,
            'halal_active_verification_id' => $active?->id,
            'halal_active_certificate_id' => $certificate?->id,
            'halal_verified_at' => $active?->effective_from,
            'halal_expires_at' => $certificate?->expires_at,
            'halal_open_report_count' => $pendingReports->count(),
            'halal_review_state' => $this->reviewStateResolver->resolve($status, $certificate, $latest, $pendingReports),
        ])->save();

        return $restaurant;
    }
}
