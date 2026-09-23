<?php

namespace App\Services\Brain;

use App\Services\RecommendationService;

/**
 * Replays a stored pool under modified weights — pure arithmetic over ≤8 candidates, never a
 * fresh scoring pass or Places call. Powers the causal deciding factor ("which single signal,
 * if removed, changes the winner?"), What-if, and Tune.
 *
 * Pool entries: ['id', 'name', 'tier', 'components' => [], 'weights' => []].
 */
final class Counterfactual
{
    /**
     * Index of the winner after applying $adjust to every candidate's weights.
     *
     * @param  array<int, array>  $pool
     * @param  callable(array $weights, array $candidate): array  $adjust
     */
    public static function winner(array $pool, callable $adjust, ?callable $eligible = null): ?int
    {
        $best = null;
        $bestKey = null;
        foreach ($pool as $index => $candidate) {
            if ($eligible && ! $eligible($candidate)) {
                continue;
            }
            $score = RecommendationService::finalFrom($candidate['components'], $adjust($candidate['weights'], $candidate));
            $key = [$candidate['tier'], $score];
            if ($bestKey === null || $key > $bestKey) {
                $best = $index;
                $bestKey = $key;
            }
        }

        return $best;
    }

    /**
     * Components whose removal flips the winner, ordered by the winner's lift on them.
     *
     * @return array<int, array{component: string, winnerIndex: int}>
     */
    public static function flips(array $pool, int $winnerIndex, array $lifts): array
    {
        $flips = [];
        // Near-ties can reorder under stored (rounded) components — only claim causality when the
        // replay agrees with the real ranking in the first place.
        if (self::winner($pool, fn (array $weights) => $weights) !== $winnerIndex) {
            return [];
        }
        $keys = array_keys($pool[$winnerIndex]['weights']);
        usort($keys, fn ($a, $b) => ($lifts[$b] ?? 0) <=> ($lifts[$a] ?? 0));

        foreach ($keys as $key) {
            $winner = self::winner($pool, function (array $weights) use ($key) {
                unset($weights[$key]);

                return $weights;
            });
            if ($winner !== null && $winner !== $winnerIndex) {
                $flips[] = ['component' => $key, 'winnerIndex' => $winner];
            }
        }

        return $flips;
    }

    /**
     * Comparative lift of each component for $index vs the pool mean:
     * lift_c = w_c/W · (x_c − mean_pool(c)) · 100
     *
     * @return array<string, float>
     */
    public static function lifts(array $pool, int $index): array
    {
        $weights = $pool[$index]['weights'];
        $total = array_sum($weights);
        if ($total <= 0) {
            return [];
        }

        $lifts = [];
        foreach ($weights as $key => $weight) {
            $values = array_values(array_filter(array_map(fn ($c) => $c['components'][$key] ?? null, $pool), fn ($v) => $v !== null));
            $mean = $values ? array_sum($values) / count($values) : 0;
            $lifts[$key] = round($weight / $total * (($pool[$index]['components'][$key] ?? 0) - $mean) * 100, 3);
        }

        return $lifts;
    }
}
