<?php

namespace App\Support;

/**
 * Search-time filter, composable with DiscoveryMode (e.g. mode=low_key + vibe=study means
 * "something low-key that's also good for studying"). Deliberately narrower than CommunityTag
 * — every case here is meant to be a real retrieval/ranking signal (a Text Search lane, a
 * cafeRelevance-style component), not just a feeling a user might describe a place with after
 * the fact. See CommunityTag for that broader, feedback-only taxonomy.
 */
enum Vibe: string
{
    case Chill = 'chill';
    case Study = 'study';
    case Dessert = 'dessert';
    case Coffee = 'coffee';
    case Brunch = 'brunch';
    case LateNight = 'late_night';

    public static function fromRequest(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
