<?php

namespace App\Services;

use App\Services\Craving\CravingIntent;
use App\Support\FoodTaxonomy;
use App\Support\ScoreWeights;

/**
 * MakanApa Recommendation v1.
 * Server-authoritative — there is no client-side scoring mirror to keep in sync (an earlier
 * iOS-side copy was removed; this is the only implementation).
 *
 * Restaurant arrays passed to this service use the shape:
 * ['id', 'name', 'signature_dish', 'food_category', 'latitude', 'longitude', 'price_level',
 *  'rating', 'is_active', 'cuisines' => string[], 'tags' => string[]]
 *
 * Preference arrays use the shape:
 * ['moodTags' => string[], 'cuisines' => string[], 'cravingIntent' => ?CravingIntent,
 *  'budgetMax' => int, 'maxDistanceKm' => float, 'latitude' => float, 'longitude' => float]
 */
class RecommendationService
{
    /** Rank weights for weighted-random pick among the top 5 candidates. */
    private const RANK_WEIGHTS = [0.40, 0.25, 0.17, 0.11, 0.07];

    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private static function tagMatchComponent(array $selected, array $candidate): float
    {
        if (empty($selected)) {
            return 0;
        }
        $matched = count(array_intersect($selected, $candidate));

        return $matched / count($selected);
    }

    /**
     * Ranking signal, not an availability guarantee: this can only credit a restaurant whose
     * *live-synced* tags/food_category/name/signature_dish happen to reflect the resolved
     * concept — a restaurant that genuinely serves it but isn't tagged/named for it scores 0
     * here, same as one that doesn't serve it at all. Dual retrieval (PlacesService's Text
     * Search call) is what actually rescues those candidates into the pool in the first place;
     * this only ranks what's already there.
     */
    private static function relevanceComponent(CravingIntent $intent, array $restaurant): float
    {
        $concept = $intent->concept;
        if ($concept !== null) {
            if (in_array($concept, $restaurant['tags'] ?? [], true)) {
                return 1.0;
            }
            $foodCategory = FoodTaxonomy::foodCategoryFor($concept);
            if ($foodCategory !== null && ($restaurant['food_category'] ?? null) === $foodCategory) {
                return 1.0;
            }
        }

        // Falls back to the raw craving text when nothing resolved to a concept — this mirrors
        // CravingIntent::primarySearchTerm(), which is exactly what PlacesService used to find
        // Text Search candidates in the first place. Without this fallback, a restaurant the
        // dual-retrieval Text Search call successfully found for an unresolved craving (e.g.
        // "roti john") could never be credited here, silently discarding the whole point of
        // that retrieval call.
        $terms = ! empty($intent->searchTerms) ? $intent->searchTerms : [$intent->raw];

        $name = strtolower($restaurant['name'] ?? '');
        $signatureDish = strtolower($restaurant['signature_dish'] ?? '');
        foreach ($terms as $term) {
            $term = strtolower(trim($term));
            if ($term === '') {
                continue;
            }
            if (($name !== '' && str_contains($name, $term)) || ($signatureDish !== '' && str_contains($signatureDish, $term))) {
                return 0.8;
            }
        }

        return 0;
    }

    private static function distanceComponent(float $distanceKm, float $maxDistanceKm): float
    {
        if ($maxDistanceKm <= 0) {
            return 0;
        }

        return max(0, 1 - $distanceKm / $maxDistanceKm);
    }

    private static function ratingComponent(?float $rating): float
    {
        if ($rating === null) {
            return 0.5;
        }

        return min(1, max(0, $rating / 5.0));
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     * @return array<int, array{restaurant: array<string, mixed>, distanceKm: float}>
     */
    public function eligibleRestaurants(array $restaurants, array $preference): array
    {
        $eligible = [];

        foreach ($restaurants as $restaurant) {
            if (! ($restaurant['is_active'] ?? true)) {
                continue;
            }
            // null budgetMax means "Anything lah" — no price filter, not "cheapest only".
            if (isset($restaurant['price_level']) && $preference['budgetMax'] !== null
                && $restaurant['price_level'] > $preference['budgetMax']) {
                continue;
            }
            // OPEN / CLOSED / UNKNOWN — only CLOSED excludes. UNKNOWN (no opening-hours data) is
            // included unpenalized; missing data isn't evidence a restaurant is unavailable.
            if (($restaurant['open_status'] ?? 'unknown') === 'closed') {
                continue;
            }
            $distanceKm = self::distanceKm(
                $preference['latitude'], $preference['longitude'],
                $restaurant['latitude'], $restaurant['longitude']
            );
            if ($distanceKm > $preference['maxDistanceKm']) {
                continue;
            }
            $eligible[] = ['restaurant' => $restaurant, 'distanceKm' => $distanceKm];
        }

        return $eligible;
    }

    public function score(array $restaurant, array $preference, float $distanceKm): float
    {
        return $this->scoreBreakdown($restaurant, $preference, $distanceKm)['final'];
    }

    /**
     * Same computation as score(), but returns every component alongside the final number —
     * used directly by score() and separately captured for the winning candidate when
     * RECOMMENDATION_DEBUG is on (see RecommendationController), so debug output can never
     * diverge from what actually scored the pick.
     *
     * @return array{final: float, weights: array<string, int>, components: array<string, float>}
     */
    public function scoreBreakdown(array $restaurant, array $preference, float $distanceKm): array
    {
        $moodTags = $preference['moodTags'] ?? [];
        $cuisines = $preference['cuisines'] ?? [];
        $cravingIntent = $preference['cravingIntent'] ?? null;
        // Broader than isRecognized(): PlacesService's Text Search still runs (via
        // CravingIntent::primarySearchTerm()'s raw-text fallback) even for an unresolved
        // craving, so scoring must still get a chance to credit whatever that search found —
        // gating this on isRecognized() alone would silently discard those candidates' only
        // relevance signal, same failure mode as the original "ice cream -> burger" bug.
        $hasCraving = $cravingIntent instanceof CravingIntent && trim($cravingIntent->raw) !== '';

        // moodTags and craving are mutually exclusive by contract (SoloRecommendationRequest
        // rejects requests with both moods and craving) — whichever is present picks its own
        // weight table.
        $weights = match (true) {
            ! empty($moodTags) => ScoreWeights::forMoodTags(),
            $hasCraving => ScoreWeights::forCraving(),
            default => ScoreWeights::default(),
        };

        $components = [];
        $activeWeights = [];

        if (! empty($moodTags)) {
            $components['mood'] = self::tagMatchComponent($moodTags, $restaurant['tags'] ?? []);
            $activeWeights[] = [$weights['mood'], $components['mood']];
        } elseif ($hasCraving) {
            $components['relevance'] = self::relevanceComponent($cravingIntent, $restaurant);
            $activeWeights[] = [$weights['relevance'], $components['relevance']];
        }
        if (! empty($cuisines) && isset($weights['cuisine'])) {
            $components['cuisine'] = self::tagMatchComponent($cuisines, $restaurant['cuisines'] ?? []);
            $activeWeights[] = [$weights['cuisine'], $components['cuisine']];
        }
        $components['budget'] = 1.0;
        $activeWeights[] = [$weights['budget'], $components['budget']];
        $components['distance'] = self::distanceComponent($distanceKm, $preference['maxDistanceKm']);
        $activeWeights[] = [$weights['distance'], $components['distance']];
        $components['rating'] = self::ratingComponent($restaurant['rating'] ?? null);
        $activeWeights[] = [$weights['rating'], $components['rating']];

        $totalWeight = array_sum(array_column($activeWeights, 0));
        $final = 0.0;
        if ($totalWeight > 0) {
            foreach ($activeWeights as [$weight, $component]) {
                $final += ($weight / $totalWeight) * $component * 100;
            }
        }

        return ['final' => $final, 'weights' => $weights, 'components' => $components];
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     * @return array<int, array{restaurant: array<string, mixed>, score: float}>
     */
    public function topCandidates(array $restaurants, array $preference, int $limit = 5): array
    {
        $eligible = $this->eligibleRestaurants($restaurants, $preference);

        $scored = array_map(fn ($pair) => [
            'restaurant' => $pair['restaurant'],
            'score' => $this->score($pair['restaurant'], $preference, $pair['distanceKm']),
            'distanceKm' => $pair['distanceKm'],
        ], $eligible);

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Weighted-random pick over ranked candidates, optionally excluding one (for reroll).
     *
     * @param  array<int, array{restaurant: array<string, mixed>, score: float}>  $candidates
     * @return array{restaurant: array<string, mixed>, score: float}|null
     */
    public function pick(array $candidates, ?array $excluded = null, ?callable $randomSource = null): ?array
    {
        $randomSource ??= fn () => mt_rand() / mt_getrandmax();

        $pool = $candidates;
        if ($excluded !== null) {
            $pool = array_values(array_filter(
                $pool,
                fn ($candidate) => $candidate['restaurant']['id'] !== $excluded['restaurant']['id']
            ));
        }

        if (empty($pool)) {
            return null;
        }

        $weights = array_slice(self::RANK_WEIGHTS, 0, count($pool));
        $totalWeight = array_sum($weights);
        $roll = $randomSource() * $totalWeight;

        foreach ($pool as $index => $candidate) {
            $weight = $weights[$index] ?? 0;
            if ($roll < $weight) {
                return $candidate;
            }
            $roll -= $weight;
        }

        return end($pool);
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     */
    public function recommend(array $restaurants, array $preference): array
    {
        $candidates = $this->topCandidates($restaurants, $preference);

        return [
            'candidates' => $candidates,
            'pick' => $this->pick($candidates),
            'craving' => $this->cravingMatchStatus($candidates, $preference['cravingIntent'] ?? null),
        ];
    }

    /**
     * @param  array<int, array{restaurant: array<string, mixed>, score: float}>  $candidates
     * @return array{query: string, matched: bool, resolvedAs: ?string, source: string, confidence: float}|null
     */
    private function cravingMatchStatus(array $candidates, ?CravingIntent $intent): ?array
    {
        if ($intent === null || trim($intent->raw) === '') {
            return null;
        }

        $matched = false;
        foreach ($candidates as $candidate) {
            if (self::relevanceComponent($intent, $candidate['restaurant']) > 0) {
                $matched = true;
                break;
            }
        }

        return [
            'query' => $intent->raw,
            'matched' => $matched,
            'resolvedAs' => $intent->concept,
            'source' => $intent->source,
            'confidence' => $intent->confidence,
        ];
    }
}
