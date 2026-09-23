<?php

namespace App\Services\Halal;

use App\Models\User;

/**
 * Quiet, internal credibility for halal contributors. Never shown to anyone — it only nudges
 * moderation priority (HalalReportService::reviewPriority()). Deliberately not a public score,
 * so there's no social incentive to game it.
 */
class ContributorCredibilityService
{
    public const TRUSTED_MIN_APPROVED = 5;

    public const TRUSTED_MAX_REJECTION_RATE = 0.10;

    /** @param 'approved'|'rejected'|'overturned' $outcome */
    public function record(User $user, string $outcome): void
    {
        $stats = array_merge(['approved' => 0, 'rejected' => 0, 'overturned' => 0], $user->contribution_stats ?? []);
        $stats[$outcome]++;

        $user->forceFill([
            'contribution_stats' => $stats,
            'trusted_contributor' => $this->qualifies($stats),
        ])->save();
    }

    public function rejectionRate(User $user): float
    {
        $stats = $user->contribution_stats ?? [];
        $decided = ($stats['approved'] ?? 0) + ($stats['rejected'] ?? 0);

        return $decided === 0 ? 0.0 : ($stats['rejected'] ?? 0) / $decided;
    }

    private function qualifies(array $stats): bool
    {
        $decided = $stats['approved'] + $stats['rejected'];

        return $stats['approved'] >= self::TRUSTED_MIN_APPROVED
            && $stats['overturned'] === 0
            && ($decided === 0 ? 0 : $stats['rejected'] / $decided) < self::TRUSTED_MAX_REJECTION_RATE;
    }
}
