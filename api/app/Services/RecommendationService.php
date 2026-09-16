<?php

namespace App\Services;

/**
 * MakanApa Recommendation v1.
 * Mirrors ios/MakanApa/Core/Recommendation/RecommendationScore.swift + RecommendationEngine.swift exactly —
 * keep both in sync.
 *
 * Restaurant arrays passed to this service use the shape:
 * ['id', 'latitude', 'longitude', 'price_level', 'rating', 'is_active', 'cuisines' => string[], 'tags' => string[]]
 *
 * Preference arrays use the shape:
 * ['moodTags' => string[], 'cuisines' => string[], 'budgetMax' => int, 'maxDistanceKm' => float,
 *  'latitude' => float, 'longitude' => float]
 */
class RecommendationService
{
    private const MOOD_WEIGHT = 30;
    private const CUISINE_WEIGHT = 25;
    private const BUDGET_WEIGHT = 15;
    private const DISTANCE_WEIGHT = 15;
    private const RATING_WEIGHT = 15;

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
            if (isset($restaurant['price_level']) && $restaurant['price_level'] > $preference['budgetMax']) {
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
        $activeWeights = [];

        $moodTags = $preference['moodTags'] ?? [];
        $cuisines = $preference['cuisines'] ?? [];

        if (! empty($moodTags)) {
            $activeWeights[] = [self::MOOD_WEIGHT, self::tagMatchComponent($moodTags, $restaurant['tags'] ?? [])];
        }
        if (! empty($cuisines)) {
            $activeWeights[] = [self::CUISINE_WEIGHT, self::tagMatchComponent($cuisines, $restaurant['cuisines'] ?? [])];
        }
        $activeWeights[] = [self::BUDGET_WEIGHT, 1.0];
        $activeWeights[] = [self::DISTANCE_WEIGHT, self::distanceComponent($distanceKm, $preference['maxDistanceKm'])];
        $activeWeights[] = [self::RATING_WEIGHT, self::ratingComponent($restaurant['rating'] ?? null)];

        $totalWeight = array_sum(array_column($activeWeights, 0));
        if ($totalWeight <= 0) {
            return 0;
        }

        $weightedSum = 0;
        foreach ($activeWeights as [$weight, $component]) {
            $weightedSum += ($weight / $totalWeight) * $component * 100;
        }

        return $weightedSum;
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
        if ($excluded !== null && count($pool) > 1) {
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

        return ['candidates' => $candidates, 'pick' => $this->pick($candidates)];
    }
}
