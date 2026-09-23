<?php

namespace App\Services\Brain;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Selera Memory + Moment Pulse → scoring components. Pure: reads DecisionBrainState only.
 *
 *  seleraFit = 0.5 + 0.5·clamp(long-term ⊕ meal-slot slice + ½·pulse)   (0.5 = no opinion)
 *  novelty   = 1.0 / 0.6 / 0.25 by how often the category was accepted in the last 5 days
 */
final class SeleraScorer
{
    public static function fit(DecisionBrainState $brain, array $restaurant): float
    {
        $now = Carbon::instance($brain->now);
        $memory = $brain->memory();
        $category = $restaurant['food_category'] ?? null;
        $cuisines = $restaurant['cuisines'] ?? [];
        $price = isset($restaurant['price_level']) ? (string) $restaurant['price_level'] : null;

        $long = $brain->tasteMature ? self::signal($brain, $memory['longTerm'] ?? [], $category, $cuisines, $price, $now) : null;

        $slotScore = null;
        $slotWeight = 0.0;
        $slice = $memory['slots'][$brain->context->mealSlot] ?? null;
        if ($brain->tasteMature && $slice) {
            $evidence = array_sum(array_map(fn ($entries) => array_sum(array_column($entries, 'n')), $slice));
            if ($evidence >= (int) Config::get('brain.slot_min_evidence', 4)) {
                $slotScore = self::signal($brain, $slice, $category, $cuisines, null, $now);
                $slotWeight = TasteMemory::confidence($evidence) * 0.5;
            }
        }

        $pulse = 0.0;
        if (Config::get('brain.features.pulse')) {
            $raw = $brain->pulse->delta('category', $category)
                + array_sum(array_map(fn ($c) => $brain->pulse->delta('cuisine', $c), $cuisines)) / max(1, count($cuisines))
                + $brain->pulse->delta('price', $price);
            $pulse = tanh($raw);
        }

        if ($long === null && $slotScore === null && $pulse == 0.0) {
            return 0.5;
        }

        $s = match (true) {
            $long !== null && $slotScore !== null => (1 - $slotWeight) * $long + $slotWeight * $slotScore,
            $long !== null => $long,
            $slotScore !== null => $slotScore,
            default => 0.0,
        };
        $s = max(-1.0, min(1.0, $s + 0.5 * $pulse));

        return round(0.5 + 0.5 * $s, 4);
    }

    /** Which slot slice (if any) actually has an opinion about this restaurant's category. */
    public static function slotAffinity(DecisionBrainState $brain, ?string $category): float
    {
        if (! $brain->tasteMature || $category === null) {
            return 0.0;
        }
        $entry = $brain->memory()['slots'][$brain->context->mealSlot]['category'][$category] ?? null;

        return ($entry && $entry['n'] >= (int) Config::get('brain.slot_min_evidence', 4))
            ? TasteMemory::effective($entry, Carbon::instance($brain->now))
            : 0.0;
    }

    public static function novelty(DecisionBrainState $brain, array $restaurant): float
    {
        $category = $restaurant['food_category'] ?? null;
        if ($category === null) {
            return 1.0;
        }

        $since = Carbon::instance($brain->now)->subDays(5);
        $times = count(array_filter(
            $brain->memory()['recent'] ?? [],
            fn ($r) => $r['category'] === $category && Carbon::parse($r['at'])->gte($since),
        ));

        return match (true) {
            $times >= 2 => 0.25,
            $times === 1 => 0.6,
            default => 1.0,
        };
    }

    /** The category the user has been repeating lately (for "different from your X streak"). */
    public static function streakCategory(DecisionBrainState $brain): ?string
    {
        $since = Carbon::instance($brain->now)->subDays(5);
        $counts = [];
        foreach ($brain->memory()['recent'] ?? [] as $r) {
            if (Carbon::parse($r['at'])->gte($since)) {
                $counts[$r['category']] = ($counts[$r['category']] ?? 0) + 1;
            }
        }
        arsort($counts);

        return ($counts && reset($counts) >= 2) ? array_key_first($counts) : null;
    }

    public static function hasRecent(DecisionBrainState $brain): bool
    {
        return ! empty($brain->memory()['recent']);
    }

    /** Average of tanh(effective) over the dimensions this restaurant has, skipping silenced traits. [-1, 1] */
    private static function signal(DecisionBrainState $brain, array $dims, ?string $category, array $cuisines, ?string $price, $now): ?float
    {
        $parts = [];

        if ($category !== null && isset($dims['category'][$category]) && ! $brain->isSilenced("category:{$category}")) {
            $parts[] = tanh(TasteMemory::effective($dims['category'][$category], $now) / 2);
        }

        $cuisineParts = [];
        foreach ($cuisines as $cuisine) {
            if (isset($dims['cuisine'][$cuisine]) && ! $brain->isSilenced("cuisine:{$cuisine}")) {
                $cuisineParts[] = tanh(TasteMemory::effective($dims['cuisine'][$cuisine], $now) / 2);
            }
        }
        if ($cuisineParts) {
            $parts[] = array_sum($cuisineParts) / count($cuisineParts);
        }

        if ($price !== null && isset($dims['price'][$price]) && ! $brain->isSilenced("price:{$price}")) {
            $parts[] = tanh(TasteMemory::effective($dims['price'][$price], $now) / 2);
        }

        return $parts ? array_sum($parts) / count($parts) : null;
    }
}
