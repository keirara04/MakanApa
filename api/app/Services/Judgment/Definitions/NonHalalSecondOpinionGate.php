<?php

namespace App\Services\Judgment\Definitions;

use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Support\Halal\HalalDecisionMethod;
use App\Support\Halal\HalalHeuristicResult;
use App\Support\Halal\HalalStatus;

/**
 * Decides whether a place is worth an AI second opinion: weak-only heuristic match, still
 * unknown (no human decision), and enough listing context that the model isn't guessing. Pure
 * so it can run inline during Google sync without any network call.
 */
final class NonHalalSecondOpinionGate
{
    public const MIN_SIGNALS = 3;

    private const GENERIC_TYPES = ['restaurant', 'food', 'point_of_interest', 'establishment', 'store'];

    public static function specificTypes(array $types): array
    {
        return array_values(array_diff($types, self::GENERIC_TYPES));
    }

    public static function signalCount(Restaurant $restaurant): int
    {
        $signals = filled($restaurant->name) ? 1 : 0;
        $signals += self::specificTypes($restaurant->google_types ?? []) !== [] ? 1 : 0;
        $signals += filled($restaurant->food_category) ? 1 : 0;
        $signals += $restaurant->cuisines()->exists() ? 1 : 0;
        $signals += filled($restaurant->signature_dish) ? 1 : 0;
        $signals += RestaurantMenuItem::where('restaurant_id', $restaurant->id)->exists() ? 1 : 0;

        return $signals;
    }

    public static function shouldAsk(Restaurant $restaurant, HalalHeuristicResult $heuristic): bool
    {
        if ($heuristic->likelyNonHalal || $heuristic->matches === []) {
            return false; // strong matches are handled deterministically; no matches = nothing to check
        }
        if ($restaurant->effectiveHalalStatus() !== HalalStatus::Unknown) {
            return false;
        }
        $method = $restaurant->activeHalalVerification?->decision_method;
        if ($method !== null && $method !== HalalDecisionMethod::Automatic) {
            return false; // a human already decided
        }

        return self::signalCount($restaurant) >= self::MIN_SIGNALS;
    }
}
