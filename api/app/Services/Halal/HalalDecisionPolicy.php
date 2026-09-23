<?php

namespace App\Services\Halal;

use App\Models\RestaurantHalalVerification;
use App\Support\Halal\HalalDecisionMethod;

/**
 * The single place that decides whether an incoming halal decision may replace the active one.
 * Trust is deliberately NOT a linear ranking of sources:
 *  - Automatic (heuristic) only ever replaces another automatic result — never human review.
 *  - RegistryVerified replaces anything except an explicit administrator override (an admin
 *    overrode for a reason — e.g. a stale registry record — so a registry re-check must not
 *    silently undo it; the admin lifts it by recording a new decision).
 *  - ModeratorReview and AdministratorOverride replace anything: between human decisions,
 *    the newest wins.
 */
class HalalDecisionPolicy
{
    public function canSupersede(?RestaurantHalalVerification $current, HalalDecisionMethod $incoming): bool
    {
        if ($current === null) {
            return true;
        }

        return match ($incoming) {
            HalalDecisionMethod::Automatic => $current->decision_method === HalalDecisionMethod::Automatic,
            HalalDecisionMethod::RegistryVerified => $current->decision_method !== HalalDecisionMethod::AdministratorOverride,
            HalalDecisionMethod::ModeratorReview, HalalDecisionMethod::AdministratorOverride => true,
        };
    }
}
