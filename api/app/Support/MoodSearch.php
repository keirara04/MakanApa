<?php

namespace App\Support;

/**
 * The cuisine moods shown abroad (Korea, the US, elsewhere) and how to search for them when the
 * nearby pool has none: a Google Text Search (query + one includedType) and a Kakao keyword
 * (KakaoLocalSearch translates it). Malaysia's dish moods aren't here and keep their old path.
 */
final class MoodSearch
{
    /** @var array<string, array{google: string, googleType: string, kakao: ?string}> */
    public const MOODS = [
        'middle_eastern' => ['google' => 'halal middle eastern restaurant', 'googleType' => 'restaurant', 'kakao' => 'kebab'],
        'indian' => ['google' => 'indian restaurant', 'googleType' => 'indian_restaurant', 'kakao' => 'indian'],
        'malay' => ['google' => 'malaysian indonesian restaurant', 'googleType' => 'restaurant', 'kakao' => 'malaysian'],
        'chicken' => ['google' => 'fried chicken', 'googleType' => 'restaurant', 'kakao' => 'fried chicken'],
        'korean' => ['google' => 'korean restaurant', 'googleType' => 'korean_restaurant', 'kakao' => null],
        'burger' => ['google' => 'burger', 'googleType' => 'hamburger_restaurant', 'kakao' => 'burger'],
        'pizza' => ['google' => 'pizza', 'googleType' => 'pizza_restaurant', 'kakao' => 'pizza'],
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
