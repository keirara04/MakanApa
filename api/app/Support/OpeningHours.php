<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * OPEN / CLOSED / UNKNOWN for a restaurant's stored `opening_hours`, worked out at read time.
 *
 * Weekly periods (Google's regularOpeningHours) are authoritative whenever present — they stay
 * correct for as long as the row lives, where the `open_now` snapshot was only true at the
 * moment of sync (up to PLACES_CACHE_HOURS earlier). A snapshot is still used when there are no
 * periods, but only while it's younger than SNAPSHOT_MAX_AGE_MINUTES; rows synced before
 * `checked_at` existed keep their legacy snapshot until the next sync rewrites them.
 *
 * Stored shape: ['open_now' => ?bool, 'periods' => ?array, 'utc_offset_minutes' => ?int,
 * 'checked_at' => ?string (ISO-8601)].
 */
final class OpeningHours
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const UNKNOWN = 'unknown';

    private const MINUTES_PER_WEEK = 7 * 24 * 60;

    private const SNAPSHOT_MAX_AGE_MINUTES = 120;

    /** @param  array<string, mixed>|null  $openingHours */
    public static function status(?array $openingHours, CarbonInterface $now): string
    {
        $openingHours ??= [];
        $periods = $openingHours['periods'] ?? null;
        $offset = $openingHours['utc_offset_minutes'] ?? null;

        if (is_array($periods) && $periods !== [] && $offset !== null) {
            return self::statusFromPeriods($periods, (int) $offset, $now);
        }

        return self::statusFromSnapshot($openingHours, $now);
    }

    /**
     * "10:00 PM" in the venue's local time when it's open right now per its weekly periods, else
     * null (closed, open 24/7, or no periods to know from). Lets search results say "Open · closes
     * 10:00 PM" without waiting for the Place Details call.
     *
     * @param  array<string, mixed>|null  $openingHours
     */
    public static function closesAt(?array $openingHours, CarbonInterface $now): ?string
    {
        $periods = $openingHours['periods'] ?? null;
        $offset = $openingHours['utc_offset_minutes'] ?? null;
        if (! is_array($periods) || $periods === [] || $offset === null) {
            return null;
        }

        $local = $now->copy()->utc()->addMinutes((int) $offset);
        $minuteOfWeek = $local->dayOfWeek * 1440 + $local->hour * 60 + $local->minute;

        foreach ($periods as $period) {
            if (! isset($period['open']['day'], $period['close'])) {
                continue;
            }
            $opensAt = self::minuteOfWeek($period['open']);
            $closesAt = self::minuteOfWeek($period['close']);
            if ($closesAt <= $opensAt) {
                $closesAt += self::MINUTES_PER_WEEK;
            }

            foreach ([$minuteOfWeek, $minuteOfWeek + self::MINUTES_PER_WEEK] as $candidate) {
                if ($candidate >= $opensAt && $candidate < $closesAt) {
                    return $local->copy()->addMinutes($closesAt - $candidate)->format('g:i A');
                }
            }
        }

        return null;
    }

    /**
     * Google periods use day 0 = Sunday. A period with no `close` means open 24/7; a period
     * whose close is earlier in the week than its open wraps past Saturday night.
     *
     * @param  array<int, array<string, mixed>>  $periods
     */
    private static function statusFromPeriods(array $periods, int $utcOffsetMinutes, CarbonInterface $now): string
    {
        $local = $now->copy()->utc()->addMinutes($utcOffsetMinutes);
        $minuteOfWeek = $local->dayOfWeek * 1440 + $local->hour * 60 + $local->minute;

        foreach ($periods as $period) {
            if (! isset($period['open']['day'])) {
                continue;
            }
            if (! isset($period['close'])) {
                return self::OPEN;
            }

            $opensAt = self::minuteOfWeek($period['open']);
            $closesAt = self::minuteOfWeek($period['close']);
            if ($closesAt <= $opensAt) {
                $closesAt += self::MINUTES_PER_WEEK;
            }

            foreach ([$minuteOfWeek, $minuteOfWeek + self::MINUTES_PER_WEEK] as $candidate) {
                if ($candidate >= $opensAt && $candidate < $closesAt) {
                    return self::OPEN;
                }
            }
        }

        return self::CLOSED;
    }

    /** @param  array<string, mixed>  $openingHours */
    private static function statusFromSnapshot(array $openingHours, CarbonInterface $now): string
    {
        $openNow = $openingHours['open_now'] ?? null;
        if (! is_bool($openNow)) {
            return self::UNKNOWN;
        }

        $checkedAt = $openingHours['checked_at'] ?? null;
        if ($checkedAt !== null && $now->copy()->subMinutes(self::SNAPSHOT_MAX_AGE_MINUTES)->gt($checkedAt)) {
            return self::UNKNOWN;
        }

        return $openNow ? self::OPEN : self::CLOSED;
    }

    /** @param  array{day: int, hour?: int, minute?: int}  $point */
    private static function minuteOfWeek(array $point): int
    {
        return (int) $point['day'] * 1440 + (int) ($point['hour'] ?? 0) * 60 + (int) ($point['minute'] ?? 0);
    }
}
