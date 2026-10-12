<?php

namespace App\Support;

/**
 * Decide's mood chips and how to search for one when the nearby pool has no match: a Google Text
 * Search (query + one includedType) and, for the moods shown in Korea, a Kakao keyword
 * (KakaoLocalSearch translates it). `label` finishes "Nothing … nearby fits right now".
 * Dish moods only count places whose name says the dish (FoodTaxonomy aliases), same as cravings.
 */
final class MoodSearch
{
    /** @var array<string, array{label: string, google: string, googleType: string, kakao: ?string}> */
    public const MOODS = [
        // Abroad: cuisines and categories.
        'middle_eastern' => ['label' => 'Middle Eastern', 'google' => 'halal middle eastern restaurant', 'googleType' => 'restaurant', 'kakao' => 'kebab'],
        'indian' => ['label' => 'Indian', 'google' => 'indian restaurant', 'googleType' => 'indian_restaurant', 'kakao' => 'indian'],
        'malay' => ['label' => 'Malaysian or Indonesian', 'google' => 'malaysian indonesian restaurant', 'googleType' => 'restaurant', 'kakao' => 'malaysian'],
        'chicken' => ['label' => 'fried chicken', 'google' => 'fried chicken', 'googleType' => 'restaurant', 'kakao' => 'fried chicken'],
        'korean' => ['label' => 'Korean', 'google' => 'korean restaurant', 'googleType' => 'korean_restaurant', 'kakao' => null],
        'burger' => ['label' => 'burger', 'google' => 'burger', 'googleType' => 'hamburger_restaurant', 'kakao' => 'burger'],
        'pizza' => ['label' => 'pizza', 'google' => 'pizza', 'googleType' => 'pizza_restaurant', 'kakao' => 'pizza'],
        // Malaysia: dishes.
        'nasi_kandar' => ['label' => 'nasi kandar', 'google' => 'nasi kandar', 'googleType' => 'restaurant', 'kakao' => null],
        'ayam_gepuk' => ['label' => 'ayam gepuk', 'google' => 'ayam gepuk', 'googleType' => 'restaurant', 'kakao' => null],
        'nasi_padang' => ['label' => 'nasi padang', 'google' => 'nasi padang', 'googleType' => 'restaurant', 'kakao' => null],
        'mee_goreng' => ['label' => 'mee goreng', 'google' => 'mee goreng', 'googleType' => 'restaurant', 'kakao' => null],
        'nasi_lemak' => ['label' => 'nasi lemak', 'google' => 'nasi lemak', 'googleType' => 'restaurant', 'kakao' => null],
        'char_kuey_teow' => ['label' => 'char kuey teow', 'google' => 'char kuey teow', 'googleType' => 'restaurant', 'kakao' => null],
        'banana_leaf_rice' => ['label' => 'banana leaf rice', 'google' => 'banana leaf rice', 'googleType' => 'restaurant', 'kakao' => null],
        'dim_sum' => ['label' => 'dim sum', 'google' => 'dim sum', 'googleType' => 'restaurant', 'kakao' => null],
        // Both.
        'healthy' => ['label' => 'healthy', 'google' => 'healthy salad', 'googleType' => 'restaurant', 'kakao' => null],
        'quick' => ['label' => 'quick', 'google' => 'fast food', 'googleType' => 'fast_food_restaurant', 'kakao' => null],
    ];

    /** The first searchable mood in a request, if any. */
    public static function first(array $moods): ?string
    {
        foreach ($moods as $mood) {
            if (isset(self::MOODS[$mood])) {
                return $mood;
            }
        }

        return null;
    }

    /** Tags, cuisines and food category together — what RecommendationService scores a mood against. */
    public static function vocabulary(array $restaurant): array
    {
        return [...($restaurant['tags'] ?? []), ...($restaurant['cuisines'] ?? []), ...array_filter([$restaurant['food_category'] ?? null])];
    }

    /** @param  array<int, array<string, mixed>>  $restaurants */
    public static function anyMatches(array $restaurants, string $mood): bool
    {
        foreach ($restaurants as $restaurant) {
            if (in_array($mood, self::vocabulary($restaurant), true)) {
                return true;
            }
        }

        return false;
    }
}
