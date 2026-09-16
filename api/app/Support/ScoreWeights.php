<?php

namespace App\Support;

/**
 * Named weight sets for RecommendationService::score(), centralized so tuning a number never
 * requires touching scoring logic itself — this is the piece most likely to get iterated on
 * once real usage data comes in.
 */
final class ScoreWeights
{
    /** Curated grid tags (Nasi Kandar/Quick/Healthy/... chips) — unchanged from the original scoring model. */
    public static function forMoodTags(): array
    {
        return ['mood' => 30, 'cuisine' => 25, 'budget' => 15, 'distance' => 15, 'rating' => 15];
    }

    /** A recognized free-text craving (confidence >= 0.6, resolved to a concept) dominates ranking. */
    public static function forCraving(): array
    {
        return ['relevance' => 45, 'distance' => 20, 'budget' => 20, 'rating' => 15];
    }

    /** No mood tags, no recognized craving ("Anything lah" or an unresolved craving). */
    public static function default(): array
    {
        return ['budget' => 15, 'distance' => 15, 'rating' => 15];
    }
}
