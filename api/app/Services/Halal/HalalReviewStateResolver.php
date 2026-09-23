<?php

namespace App\Services\Halal;

use App\Models\RestaurantHalalCertificate;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantSubmission;
use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;
use App\Support\Halal\HalalVerificationState;
use Illuminate\Support\Collection;

/**
 * Internal review state, in priority order: conflicting evidence > pending review >
 * re-verify required > expiring > clear. Never changes the public status — reports only ever
 * raise moderation attention.
 */
class HalalReviewStateResolver
{
    public const EXPIRING_WINDOW_DAYS = 30;

    public const MASS_REPORT_THRESHOLD = 5;

    /**
     * @param  Collection<int, RestaurantSubmission>  $pendingReports  pending halal_report submissions
     */
    public function resolve(
        HalalStatus $effectiveStatus,
        ?RestaurantHalalCertificate $certificate,
        ?RestaurantHalalVerification $latestVerification,
        Collection $pendingReports,
    ): HalalReviewState {
        if ($pendingReports->isNotEmpty()) {
            $recent = $pendingReports->filter(fn (RestaurantSubmission $s) => $s->created_at?->gte(now()->subDay()));
            $disagrees = $effectiveStatus !== HalalStatus::Unknown
                && $pendingReports->contains(fn (RestaurantSubmission $s) => $s->halal_claim !== $effectiveStatus);
            $claimsDiffer = $pendingReports->pluck('halal_claim')->unique()->count() > 1;

            if ($disagrees || $claimsDiffer || $recent->count() >= self::MASS_REPORT_THRESHOLD) {
                return HalalReviewState::ConflictingEvidence;
            }

            return HalalReviewState::PendingReview;
        }

        $lapsedCertified = $latestVerification !== null
            && $latestVerification->status === HalalStatus::Certified
            && in_array($latestVerification->state, [HalalVerificationState::Expired, HalalVerificationState::Revoked], true);

        if (($certificate !== null && $certificate->isExpired()) || ($effectiveStatus === HalalStatus::Unknown && $lapsedCertified)) {
            return HalalReviewState::ReverifyRequired;
        }

        if ($certificate !== null && $certificate->expires_at->lte(today()->addDays(self::EXPIRING_WINDOW_DAYS))) {
            return HalalReviewState::Expiring;
        }

        return HalalReviewState::Clear;
    }
}
