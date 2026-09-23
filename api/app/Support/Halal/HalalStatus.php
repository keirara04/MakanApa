<?php

namespace App\Support\Halal;

/**
 * Public halal status of a restaurant. Deliberately never carries moderation state
 * (pending/conflicting/expiring) — that lives in HalalReviewState so this stays a pure
 * statement about the evidence. Wording rule: only `Certified` may ever be labelled "Halal";
 * `MuslimFriendly` must never drift into a synonym for it (see HalalPresenter).
 */
enum HalalStatus: string
{
    case Certified = 'certified';
    case MuslimFriendly = 'muslim_friendly';
    case NonHalal = 'non_halal';
    case Unknown = 'unknown';

    /** Tie-breaker strength used by RecommendationService when the halal filter is on. */
    public function confidence(): float
    {
        return match ($this) {
            self::Certified => 1.0,
            self::MuslimFriendly => 0.7,
            self::NonHalal, self::Unknown => 0.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Certified => 'Certified',
            self::MuslimFriendly => 'Muslim-friendly',
            self::NonHalal => 'Non-halal',
            self::Unknown => 'Unknown',
        };
    }

    /** Statuses a reporter/moderator may assert — `Unknown` is only ever the absence of one. */
    public static function claimable(): array
    {
        return [self::Certified, self::MuslimFriendly, self::NonHalal];
    }
}
