<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Weekly signup cohorts and how many came back: a user is "retained on day N" if they opened
 * the app (an app_sessions row) during the 24h starting N days after they signed up, and "in
 * week K" if they did during days 7K…7K+6. A cohort only gets a number for a window that has
 * fully passed for that user — otherwise young cohorts would read as churned.
 */
class RetentionReport
{
    public const SEGMENTS = [
        'all' => 'Everyone',
        'registered' => 'Accounts',
        'guests' => 'Guests',
    ];

    /** Day-N windows, then week-K windows (as [label => [startDay, lengthDays]]). */
    public const WINDOWS = [
        'd1' => [1, 1],
        'd7' => [7, 1],
        'd30' => [30, 1],
        'w1' => [7, 7],
        'w2' => [14, 7],
        'w3' => [21, 7],
        'w4' => [28, 7],
    ];

    /**
     * @return list<array{cohort: string, size: int, windows: array<string, array{eligible: int, retained: int, rate: ?float}>}>
     */
    public function cohorts(int $weeks, string $segment): array
    {
        $segment = array_key_exists($segment, self::SEGMENTS) ? $segment : 'all';
        $weeks = max(1, min(26, $weeks));

        return Cache::remember("admin:retention:{$weeks}:{$segment}", now()->addMinutes(10), fn () => $this->compute($weeks, $segment));
    }

    /**
     * @return list<array{cohort: string, size: int, windows: array<string, array{eligible: int, retained: int, rate: ?float}>}>
     */
    private function compute(int $weeks, string $segment): array
    {
        $selects = [];
        foreach (self::WINDOWS as $key => [$start, $length]) {
            $end = $start + $length;
            // Eligible = the window has fully elapsed. Retained = at least one session inside it
            // (served by the app_sessions (user_id, started_at) index).
            $selects[] = "count(*) filter (where u.created_at <= now() - interval '{$end} days') as {$key}_eligible";
            $selects[] = "count(*) filter (where u.created_at <= now() - interval '{$end} days' and exists (
                select 1 from app_sessions s
                where s.user_id = u.id
                  and s.started_at >= u.created_at + interval '{$start} days'
                  and s.started_at < u.created_at + interval '{$end} days'
            )) as {$key}_retained";
        }

        $segmentFilter = match ($segment) {
            'registered' => 'and u.is_guest = false',
            'guests' => 'and u.is_guest = true',
            default => '',
        };

        $rows = DB::select(
            "select date_trunc('week', u.created_at) as cohort, count(*) as size, ".implode(', ', $selects)."
            from users u
            where u.deleted_at is null
              and u.created_at >= date_trunc('week', now()) - make_interval(weeks => ?) {$segmentFilter}
            group by 1
            order by 1 desc",
            [$weeks - 1],
        );

        return array_map(function (object $row) {
            $windows = [];
            foreach (array_keys(self::WINDOWS) as $key) {
                $eligible = (int) $row->{"{$key}_eligible"};
                $retained = (int) $row->{"{$key}_retained"};
                $windows[$key] = ['eligible' => $eligible, 'retained' => $retained, 'rate' => $eligible > 0 ? (float) $retained / $eligible : null];
            }

            return ['cohort' => substr((string) $row->cohort, 0, 10), 'size' => (int) $row->size, 'windows' => $windows];
        }, $rows);
    }
}
