<?php

namespace App\Support\Halal;

/**
 * How a verification's status was decided. Trust between methods is not a linear ranking —
 * supersession rules live in HalalDecisionPolicy, never inline at call sites.
 */
enum HalalDecisionMethod: string
{
    case Automatic = 'automatic';
    case ModeratorReview = 'moderator_review';
    case AdministratorOverride = 'administrator_override';
    case RegistryVerified = 'registry_verified';

    public function isHumanReviewed(): bool
    {
        return $this !== self::Automatic;
    }

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::ModeratorReview => 'Moderator reviewed',
            self::AdministratorOverride => 'Administrator override',
            self::RegistryVerified => 'Registry verified',
        };
    }
}
