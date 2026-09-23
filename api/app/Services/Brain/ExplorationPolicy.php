<?php

namespace App\Services\Brain;

use Illuminate\Support\Facades\Config;

/**
 * Adaptive exploration: how adventurous to be *right now*, then a softmax pick inside the top
 * relevance tier only (exploration can never break a craving match).
 *
 * need rises with: a new profile, repetition, a novelty drive, a close/ambiguous pool,
 *                  rerolls this session (up to 4), the surprise_me lens
 * need falls with: a strong explicit craving, an obvious winner, the quick_one lens
 * fatigue (≥5 rerolls/tunes): need = 0 and the pick is the *safest* high-fit option.
 */
final class ExplorationPolicy
{
    public static function baseNeed(float $cravingStrength, string $seleraStage, bool $hasStreak, float $noveltyDrive, int $sessionActions): float
    {
        $need = match ($seleraStage) {
            'starting' => 0.35,
            'learning' => 0.3,
            'knowing' => 0.25,
            default => 0.2,
        };
        $need += $hasStreak ? 0.15 : 0.0;
        $need += min(0.2, max(0.0, $noveltyDrive) * 0.1);
        $need += min(4, $sessionActions) * 0.08;
        $need *= (1 - $cravingStrength);

        return round(max(0.0, min(1.0, $need)), 3);
    }

    /**
     * Final need for this pool: base need, adjusted by pool ambiguity and the lens clamp.
     *
     * @param  array<int, array>  $pool
     */
    public static function need(DecisionBrainState $brain, array $pool): float
    {
        if ($brain->fatigueMode || ! Config::get('brain.features.exploration')) {
            return 0.0;
        }

        $top = self::topTier($pool);
        $scores = array_column($top, 'score');
        $ambiguity = 0.0;
        if (count($scores) >= 2) {
            $mean = array_sum($scores) / count($scores);
            $sd = sqrt(array_sum(array_map(fn ($s) => ($s - $mean) ** 2, $scores)) / count($scores));
            // sd ≲ 2 points → near-tie (ambiguity 1); sd ≳ 12 → an obvious winner (0).
            $ambiguity = max(0.0, min(1.0, (12 - $sd) / 10));
        }

        $need = $brain->baseExploration * (0.6 + 0.6 * $ambiguity);

        $lens = $brain->lens ? Config::get("brain.lenses.{$brain->lens->value}", []) : [];
        if (isset($lens['min_exploration'])) {
            $need = max($need, (float) $lens['min_exploration']);
        }
        if (isset($lens['max_exploration'])) {
            $need = min($need, (float) $lens['max_exploration']);
        }
        if (in_array('safer', $brain->tunes, true)) {
            $need = 0.0;
        }
        if (in_array('adventurous', $brain->tunes, true)) {
            $need = max($need, 0.8);
        }

        return round(max(0.0, min(1.0, $need)), 3);
    }

    /**
     * Softmax over the top tier with temperature from $need. Returns the chosen pool index and
     * every candidate's selection probability (0 outside the top tier) for the Decision Trace.
     *
     * @param  array<int, array>  $pool
     * @return array{index: ?int, probabilities: array<int, float>}
     */
    public static function pick(array $pool, float $need, bool $fatigue, ?callable $randomSource = null): array
    {
        if ($pool === []) {
            return ['index' => null, 'probabilities' => []];
        }

        $tier = max(array_column($pool, 'relevanceTier'));
        $eligible = array_keys(array_filter($pool, fn ($c) => $c['relevanceTier'] === $tier));

        if ($fatigue) {
            // "Okay lah, enough choosing" — among near-top scores, the most reliable option.
            $best = max(array_map(fn ($i) => $pool[$i]['score'], $eligible));
            $safe = array_values(array_filter($eligible, fn ($i) => $pool[$i]['score'] >= $best - 6));
            usort($safe, fn ($a, $b) => self::safety($pool[$b]) <=> self::safety($pool[$a]));
            $probabilities = array_fill(0, count($pool), 0.0);
            $probabilities[$safe[0]] = 1.0;

            return ['index' => $safe[0], 'probabilities' => $probabilities];
        }

        $tMin = (float) Config::get('brain.exploration.temperature_min', 1.0);
        $tMax = (float) Config::get('brain.exploration.temperature_max', 14.0);
        $temperature = $tMin + ($tMax - $tMin) * $need;

        $maxScore = max(array_map(fn ($i) => $pool[$i]['score'], $eligible));
        $weights = [];
        foreach ($eligible as $i) {
            $weights[$i] = exp(($pool[$i]['score'] - $maxScore) / $temperature);
        }
        $total = array_sum($weights);

        $probabilities = array_fill(0, count($pool), 0.0);
        foreach ($weights as $i => $w) {
            $probabilities[$i] = $w / $total;
        }

        $randomSource ??= fn () => mt_rand() / mt_getrandmax();
        $roll = $randomSource();
        foreach ($weights as $i => $w) {
            $roll -= $w / $total;
            if ($roll < 0) {
                return ['index' => $i, 'probabilities' => $probabilities];
            }
        }

        return ['index' => array_key_last($weights), 'probabilities' => $probabilities];
    }

    /** @param  array<int, array>  $pool */
    private static function topTier(array $pool): array
    {
        if ($pool === []) {
            return [];
        }
        $tier = max(array_column($pool, 'relevanceTier'));

        return array_values(array_filter($pool, fn ($c) => $c['relevanceTier'] === $tier));
    }

    private static function safety(array $candidate): float
    {
        $restaurant = $candidate['restaurant'];
        $rating = ($restaurant['rating'] ?? 3.5) / 5;
        $open = match ($restaurant['open_status'] ?? 'unknown') {
            'open' => 1.0,
            default => 0.5,
        };
        $volume = min(1.0, log10(max(1, (int) ($restaurant['user_rating_count'] ?? 0))) / 3);

        return $rating * 0.5 + $open * 0.3 + $volume * 0.2;
    }
}
