<?php

namespace App\Support;

/**
 * "What should I eat" formatting, shared across Solo/reroll/future-Geng/history —
 * not controller logic, since every recommendation surface needs the same priority.
 */
final class RecommendationHeadline
{
    /** @var array<string, string> */
    private const CATEGORY_LABELS = [
        'burger' => 'Burgers',
        'chicken' => 'Chicken',
        'pizza' => 'Pizza',
        'sandwich' => 'Sandwiches',
        'ramen' => 'Ramen',
        'sushi' => 'Sushi',
        'seafood' => 'Seafood',
        'steak' => 'Steak',
        'bbq' => 'BBQ',
        'bakery' => 'Bakery',
        'dessert' => 'Desserts',
        'cafe' => 'Cafe',
        'drinks' => 'Drinks',
        'breakfast' => 'Breakfast',
        'fast_food' => 'Fast Food',
    ];

    /**
     * Priority: signature dish (real per-restaurant data) → food category → cuisine → fallback.
     *
     * @param  array<string, mixed>  $restaurant  shape from Restaurant::toRecommendationArray()
     */
    public static function for(array $restaurant): string
    {
        if (! empty($restaurant['signature_dish'])) {
            return strtoupper($restaurant['signature_dish']).'.';
        }

        $category = $restaurant['food_category'] ?? null;
        if ($category !== null && isset(self::CATEGORY_LABELS[$category])) {
            return strtoupper(self::CATEGORY_LABELS[$category]).'.';
        }

        return ! empty($restaurant['cuisines'][0]) ? strtoupper($restaurant['cuisines'][0]).'.' : 'MAKAN.';
    }

    /** Human label for a food_category ("fast_food" → "Fast Food"), used in Makan Brain copy. */
    public static function categoryLabel(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        return self::CATEGORY_LABELS[$category] ?? ucwords(str_replace('_', ' ', $category));
    }
}
