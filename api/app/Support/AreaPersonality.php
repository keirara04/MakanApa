<?php

namespace App\Support;

/**
 * Derives Nearby's "this area is giving..." tags from the same aggregate counts the area
 * summary already computed — kept separate from NearbyController so the minimum-sample-size
 * guards are easy to unit test in isolation. Returns plain {key, label} data, never emoji/prose
 * — iOS decides how each key is actually rendered.
 */
class AreaPersonality
{
    /** Below this many places, a budget ratio is a coincidence, not a real area trait. */
    private const MIN_PLACE_COUNT_FOR_BUDGET_TAG = 5;

    private const BUDGET_FRIENDLY_RATIO = 0.5;

    /** A single food category needs at least this many places to call the area "X-heavy". */
    private const MIN_CATEGORY_COUNT_FOR_TAG = 3;

    /**
     * @param  array<int, array{label: string, count: int}>  $topCategories  already sorted
     *                                                                       descending by count
     * @return array<int, array{key: string, label: string}>
     */
    public static function forSummary(int $placeCount, int $budgetFriendlyCount, array $topCategories): array
    {
        if ($placeCount === 0) {
            return [];
        }

        $tags = [];

        if ($placeCount >= self::MIN_PLACE_COUNT_FOR_BUDGET_TAG
            && ($budgetFriendlyCount / $placeCount) >= self::BUDGET_FRIENDLY_RATIO) {
            $tags[] = ['key' => 'budget_friendly', 'label' => 'Budget-friendly'];
        }

        $topCategory = $topCategories[0] ?? null;
        if ($topCategory !== null && $topCategory['count'] >= self::MIN_CATEGORY_COUNT_FOR_TAG) {
            $tags[] = ['key' => 'category_heavy', 'label' => "{$topCategory['label']}-heavy"];
        }

        return $tags;
    }
}
