<?php

namespace App\Services\Brain;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Pure Selera Memory math — no DB, no clock. The same apply() drives both the incremental
 * update (TasteEventRecorder) and the full replay (TasteProfileBuilder), which is what makes a
 * rebuilt profile byte-identical to the live one.
 *
 * Every affinity is {v: value, n: evidence, t: last signal (ISO)}; decay is applied lazily
 * against the *event's* timestamp on write, and against "now" on read.
 */
final class TasteMemory
{
    public const TASTE_DIMENSIONS = ['category', 'cuisine', 'price', 'vibe'];

    public const SLOT_DIMENSIONS = ['category', 'cuisine'];

    /** @return array{memory: array, muted: array, corrections: array, signal_count: int} */
    public static function empty(): array
    {
        return [
            'memory' => ['longTerm' => [], 'slots' => [], 'distance' => ['ewma' => null, 'n' => 0], 'recent' => []],
            'muted' => [],
            'corrections' => [],
            'signal_count' => 0,
        ];
    }

    /**
     * @param  array{memory: array, muted: array, corrections: array, signal_count: int}  $state
     * @param  array<string, mixed>  $event  a taste_events row as an array
     */
    public static function apply(array $state, array $event): array
    {
        if ($event['signal'] === 'reset') {
            return self::empty();
        }

        $at = Carbon::parse($event['created_at']);
        $meta = is_array($event['metadata'] ?? null) ? $event['metadata'] : (json_decode((string) ($event['metadata'] ?? ''), true) ?: []);

        if (! empty($meta['primary'])) {
            $state['signal_count']++;
        }

        $scope = $event['scope'] ?? 'both';
        $dimension = $event['dimension'];
        $key = (string) ($event['dimension_key'] ?? '');
        $value = (float) $event['value'];

        if ($event['signal'] === 'trait_mute') {
            $state['muted'] = array_values(array_unique([...$state['muted'], $key]));

            return $state;
        }

        if ($event['signal'] === 'trait_feedback') {
            $state['corrections'][$key] = $event['detail'];
            [$dim, $dimKey] = array_pad(explode(':', $key, 2), 2, '');
            if (in_array($dim, self::TASTE_DIMENSIONS, true) && $dimKey !== '') {
                $state['memory']['longTerm'][$dim][$dimKey] = self::bump($state['memory']['longTerm'][$dim][$dimKey] ?? null, $value, $at);
            } elseif ($key === 'distance:close') {
                $ewma = $state['memory']['distance']['ewma'];
                if ($ewma !== null) {
                    $state['memory']['distance']['ewma'] = round($ewma * ($event['detail'] === 'more' ? 0.85 : 1.15), 4);
                }
            }

            return $state;
        }

        if ($scope === 'pulse') {
            return $state; // Moment Pulse is read straight from recent events, never materialized.
        }

        if (in_array($dimension, self::TASTE_DIMENSIONS, true) && $key !== '') {
            $state['memory']['longTerm'][$dimension][$key] = self::bump($state['memory']['longTerm'][$dimension][$key] ?? null, $value, $at);

            if ($event['signal'] === 'accept' && in_array($dimension, self::SLOT_DIMENSIONS, true)) {
                foreach (array_filter([$event['slot'] ?? null, ! empty($meta['weekend']) ? 'weekend' : null]) as $slice) {
                    $state['memory']['slots'][$slice][$dimension][$key] = self::bump($state['memory']['slots'][$slice][$dimension][$key] ?? null, $value, $at);
                }
            }

            if ($event['signal'] === 'accept' && $dimension === 'category') {
                $recent = $state['memory']['recent'];
                $recent[] = ['category' => $key, 'at' => $at->toIso8601String()];
                $state['memory']['recent'] = array_slice($recent, -(int) Config::get('brain.recent_size', 10));
            }
        }

        if ($dimension === 'distance') {
            $distance = $state['memory']['distance'];
            if ($key === 'km') {
                $alpha = (float) Config::get('brain.distance_ewma_alpha', 0.3);
                $distance['ewma'] = round($distance['ewma'] === null ? $value : $alpha * $value + (1 - $alpha) * $distance['ewma'], 4);
                $distance['n']++;
            } elseif ($key === 'pull' && $distance['ewma'] !== null) {
                $distance['ewma'] = round($distance['ewma'] * $value, 4);
            }
            $state['memory']['distance'] = $distance;
        }

        return $state;
    }

    /** Decay the stored value to $at, then add. */
    public static function bump(?array $entry, float $value, CarbonInterface $at): array
    {
        $decayed = $entry === null ? 0.0 : self::decay((float) $entry['v'], Carbon::parse($entry['t']), $at);

        return [
            'v' => round($decayed + $value, 4),
            'n' => ($entry['n'] ?? 0) + 1,
            't' => $at->toIso8601String(),
        ];
    }

    public static function decay(float $value, CarbonInterface $from, CarbonInterface $to): float
    {
        $days = max(0, $from->diffInSeconds($to, false)) / 86400;

        return $value * (0.5 ** ($days / (float) Config::get('brain.half_life_days', 21)));
    }

    public static function confidence(int $evidence): float
    {
        $k = (int) Config::get('brain.confidence_k', 4);

        return $evidence / ($evidence + $k);
    }

    /** Decayed-to-now value × confidence. 0 for unknown. */
    public static function effective(?array $entry, CarbonInterface $now): float
    {
        if ($entry === null) {
            return 0.0;
        }

        return self::decay((float) $entry['v'], Carbon::parse($entry['t']), $now) * self::confidence((int) $entry['n']);
    }
}
