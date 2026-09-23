<?php

namespace App\Support;

/**
 * Explicit "how do I want to decide today" intent — replaces guessing things like budget from
 * the calendar. A lens outranks context but never outranks constraints or the craving tier.
 */
enum Lens: string
{
    case CheapToday = 'cheap_today';
    case TreatMyself = 'treat_myself';
    case SurpriseMe = 'surprise_me';
    case QuickOne = 'quick_one';
    case CommunityFavs = 'community_favs';

    public static function fromRequest(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
