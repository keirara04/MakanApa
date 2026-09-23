<?php

namespace App\Services\Brain;

use Illuminate\Support\Facades\Config;

/**
 * Keeps the stored pool from being 6 nasi kandar shops — otherwise reroll and Tune have nothing
 * real to move to. At most N per dominant category and per brand/chain, backfilled from the
 * next best in order. Skipped entirely for an explicit recognized craving: if you asked for
 * nasi kandar, 6 nasi kandar shops is exactly right.
 */
final class DiversityFilter
{
    /**
     * @param  array<int, array>  $ranked  sorted tier-then-score (RecommendationService::rankAll)
     * @return array{pool: array<int, array>, displaced: int}
     */
    public static function apply(array $ranked, int $poolSize, bool $explicitCraving, array $knownChains): array
    {
        if ($explicitCraving || ! Config::get('brain.features.diversity')) {
            return ['pool' => array_slice($ranked, 0, $poolSize), 'displaced' => 0];
        }

        $maxCategory = (int) Config::get('brain.diversity.max_per_category', 2);
        $maxBrand = (int) Config::get('brain.diversity.max_per_brand', 2);
        $categories = [];
        $brands = [];
        $pool = [];
        $overflow = [];

        foreach ($ranked as $candidate) {
            if (count($pool) >= $poolSize) {
                break;
            }
            $category = $candidate['restaurant']['food_category'] ?? null;
            $brand = self::brand($candidate['restaurant']['name'] ?? '', $knownChains);

            $categoryFull = $category !== null && ($categories[$category] ?? 0) >= $maxCategory;
            $brandFull = ($brands[$brand] ?? 0) >= $maxBrand;

            if ($categoryFull || $brandFull) {
                $overflow[] = $candidate;

                continue;
            }

            $pool[] = $candidate;
            if ($category !== null) {
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
            $brands[$brand] = ($brands[$brand] ?? 0) + 1;
        }

        $displaced = count($overflow);
        foreach ($overflow as $candidate) {
            if (count($pool) >= $poolSize) {
                break;
            }
            $pool[] = $candidate; // not enough variety nearby — fall back to the best remaining
            $displaced--;
        }

        usort($pool, fn ($a, $b) => [$b['relevanceTier'], $b['score']] <=> [$a['relevanceTier'], $a['score']]);

        return ['pool' => $pool, 'displaced' => max(0, $displaced)];
    }

    /** Known chain name, else the first two words of the name ("Nasi Kandar Pelita Ampang" → "nasi kandar"). */
    public static function brand(string $name, array $knownChains): string
    {
        $lower = strtolower(trim($name));
        foreach ($knownChains as $chain) {
            if ($chain !== '' && str_contains($lower, strtolower($chain))) {
                return 'chain:'.strtolower($chain);
            }
        }

        return implode(' ', array_slice(preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower)) ?: [], 0, 2));
    }
}
