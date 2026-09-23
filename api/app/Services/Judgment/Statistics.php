<?php

namespace App\Services\Judgment;

/** Small, dependency-free math for aggregating samples and deriving confidence in code. */
final class Statistics
{
    /** @param list<float> $values */
    public static function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /** Population standard deviation — 0 for a single sample. */
    public static function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = self::mean($values);

        return sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $n);
    }

    /**
     * Normalizes a map of non-negative weights to sum to 1. Negative/non-numeric values count
     * as 0. Returns null when nothing is positive (the model gave no usable distribution).
     *
     * @param  array<string, mixed>  $weights
     * @return array<string, float>|null
     */
    public static function normalize(array $weights): ?array
    {
        $clean = array_map(fn ($v) => is_numeric($v) ? max(0.0, (float) $v) : 0.0, $weights);
        $sum = array_sum($clean);
        if ($sum <= 0) {
            return null;
        }

        return array_map(fn ($v) => $v / $sum, $clean);
    }

    /**
     * Averages several distributions over the same keys.
     *
     * @param  list<array<string, float>>  $distributions
     * @return array<string, float>
     */
    public static function averageDistributions(array $distributions): array
    {
        $keys = array_keys($distributions[0]);
        $avg = [];
        foreach ($keys as $key) {
            $avg[$key] = self::mean(array_map(fn ($d) => $d[$key] ?? 0.0, $distributions));
        }

        return $avg;
    }

    /**
     * 1 - normalized Shannon entropy: 1 = all mass on one outcome, 0 = perfectly flat.
     * Derived from the distribution itself — the model is never asked to rate its own confidence.
     */
    public static function concentration(array $distribution): float
    {
        $k = count($distribution);
        if ($k < 2) {
            return 1.0;
        }
        $entropy = 0.0;
        foreach ($distribution as $p) {
            if ($p > 0) {
                $entropy -= $p * log($p);
            }
        }

        return max(0.0, min(1.0, 1 - $entropy / log($k)));
    }

    /** Top probability minus the runner-up. */
    public static function margin(array $distribution): float
    {
        $sorted = array_values($distribution);
        rsort($sorted);

        return $sorted[0] - ($sorted[1] ?? 0.0);
    }

    public static function argmax(array $distribution): string
    {
        arsort($distribution);

        return (string) array_key_first($distribution);
    }
}
