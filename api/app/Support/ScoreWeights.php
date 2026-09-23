<?php

namespace App\Support;

/**
 * Named weight sets for RecommendationService::score(), centralized so tuning a number never
 * requires touching scoring logic itself — this is the piece most likely to get iterated on
 * once real usage data comes in.
 */
final class ScoreWeights
{
    /**
     * Additive DiscoveryMode weights — merged into the same active-weights pool as mood/craving/
     * budget/distance/rating, not a replacement table. Deliberately small per mode (first cut):
     * only the signals actually needed to make that mode feel distinct, not every conceivable one.
     * `nonChain` ships at weight 0 everywhere — see RecommendationService::nonChainBonusComponent()
     * doc comment for why.
     */
    public static function discoveryOverlay(DiscoveryMode $mode): array
    {
        return match ($mode) {
            DiscoveryMode::LowKey => ['reviewVolumeBonus' => 15, 'community' => 15, 'nonChain' => 0],
            DiscoveryMode::Cafe => ['cafeRelevance' => 20, 'community' => 15, 'nonChain' => 0],
            DiscoveryMode::Popular => ['popularityBonus' => 20, 'community' => 10],
            DiscoveryMode::CheapEats => ['cheapEatsFit' => 20],
            DiscoveryMode::LateNight => ['lateNightFit' => 20],
            DiscoveryMode::Normal => ['community' => 10],
        };
    }

    /** Type/tag-based match only — CommunityTag evidence informs UI copy, not ranking weight. */
    public static function vibeOverlay(Vibe $vibe): array
    {
        return ['vibeRelevance' => 10];
    }

    /**
     * Not mode-gated: layered on top of whichever discoveryOverlay() applies, whenever
     * personalFitComponent is actually present (installation has enough accept history).
     */
    public static function personalFitOverlay(): array
    {
        return ['personalFit' => 15];
    }

    /**
     * Only while the halal-only filter is on. Deliberately tiny — a tie-breaker among similar
     * candidates, never enough to overpower the user's actual food preference.
     */
    public static function halalOverlay(): array
    {
        return ['halalConfidence' => 2];
    }

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
