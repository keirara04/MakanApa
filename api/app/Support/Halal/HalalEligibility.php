<?php

namespace App\Support\Halal;

/**
 * The one hard halal rule every surface shares — Decide, Nearby's map and pick, and search:
 * with halal-only on, confirmed non-halal places are hidden; `unknown` stays in (with its
 * "help verify" badge), since missing evidence isn't evidence of non-halal. Reads the
 * effective (expiry-applied) status from Restaurant::toRecommendationArray()-shaped rows.
 */
final class HalalEligibility
{
    /** @param  array<string, mixed>  $restaurant */
    public static function isNonHalal(array $restaurant): bool
    {
        return ($restaurant['halal_status'] ?? HalalStatus::Unknown->value) === HalalStatus::NonHalal->value;
    }

    /** @param  array<string, mixed>  $restaurant */
    public static function allows(array $restaurant, bool $halalOnly): bool
    {
        return ! $halalOnly || ! self::isNonHalal($restaurant);
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     * @return array<int, array<string, mixed>>
     */
    public static function filter(array $restaurants, bool $halalOnly): array
    {
        if (! $halalOnly) {
            return $restaurants;
        }

        return array_values(array_filter($restaurants, fn (array $restaurant) => ! self::isNonHalal($restaurant)));
    }
}
