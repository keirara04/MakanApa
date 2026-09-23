<?php

namespace Tests\Unit\Support;

use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OpeningHoursTest extends TestCase
{
    private const MYT_OFFSET = 480;

    /** Mon–Fri 11:00–22:00 local. */
    private function weekdayLunchToDinner(): array
    {
        return array_map(fn (int $day) => [
            'open' => ['day' => $day, 'hour' => 11, 'minute' => 0],
            'close' => ['day' => $day, 'hour' => 22, 'minute' => 0],
        ], [1, 2, 3, 4, 5]);
    }

    /** $local is Malaysia time; converted to the UTC instant the server would see. */
    private function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Asia/Kuala_Lumpur')->utc();
    }

    public function test_open_inside_a_period_in_local_time(): void
    {
        $hours = ['periods' => $this->weekdayLunchToDinner(), 'utc_offset_minutes' => self::MYT_OFFSET];

        // Wednesday 2026-09-23 12:30 MYT is 04:30 UTC — only open if the offset is applied.
        $this->assertSame(OpeningHours::OPEN, OpeningHours::status($hours, $this->at('2026-09-23 12:30')));
    }

    public function test_closed_outside_every_period_even_when_the_snapshot_said_open(): void
    {
        $hours = ['open_now' => true, 'periods' => $this->weekdayLunchToDinner(), 'utc_offset_minutes' => self::MYT_OFFSET];

        $this->assertSame(OpeningHours::CLOSED, OpeningHours::status($hours, $this->at('2026-09-23 23:15')));
        $this->assertSame(OpeningHours::CLOSED, OpeningHours::status($hours, $this->at('2026-09-27 12:00'))); // Sunday
    }

    public function test_close_time_is_exclusive(): void
    {
        $hours = ['periods' => $this->weekdayLunchToDinner(), 'utc_offset_minutes' => self::MYT_OFFSET];

        $this->assertSame(OpeningHours::CLOSED, OpeningHours::status($hours, $this->at('2026-09-23 22:00')));
    }

    public function test_period_that_runs_past_midnight_stays_open_after_midnight(): void
    {
        // Saturday 18:00 → Sunday 03:00 (wraps the week boundary).
        $hours = [
            'periods' => [['open' => ['day' => 6, 'hour' => 18, 'minute' => 0], 'close' => ['day' => 0, 'hour' => 3, 'minute' => 0]]],
            'utc_offset_minutes' => self::MYT_OFFSET,
        ];

        $this->assertSame(OpeningHours::OPEN, OpeningHours::status($hours, $this->at('2026-09-26 23:00')));
        $this->assertSame(OpeningHours::OPEN, OpeningHours::status($hours, $this->at('2026-09-27 02:30')));
        $this->assertSame(OpeningHours::CLOSED, OpeningHours::status($hours, $this->at('2026-09-27 03:30')));
    }

    public function test_period_without_close_means_open_all_the_time(): void
    {
        $hours = ['periods' => [['open' => ['day' => 0, 'hour' => 0, 'minute' => 0]]], 'utc_offset_minutes' => self::MYT_OFFSET];

        $this->assertSame(OpeningHours::OPEN, OpeningHours::status($hours, $this->at('2026-09-23 04:00')));
    }

    public function test_fresh_snapshot_is_used_when_there_are_no_periods(): void
    {
        $now = $this->at('2026-09-23 12:00');
        $hours = ['open_now' => false, 'checked_at' => $now->subMinutes(30)->toIso8601String()];

        $this->assertSame(OpeningHours::CLOSED, OpeningHours::status($hours, $now));
    }

    public function test_stale_snapshot_becomes_unknown(): void
    {
        $now = $this->at('2026-09-23 12:00');
        $hours = ['open_now' => true, 'checked_at' => $now->subHours(5)->toIso8601String()];

        $this->assertSame(OpeningHours::UNKNOWN, OpeningHours::status($hours, $now));
    }

    public function test_legacy_snapshot_without_checked_at_is_still_trusted(): void
    {
        $this->assertSame(OpeningHours::OPEN, OpeningHours::status(['open_now' => true], $this->at('2026-09-23 12:00')));
    }

    public function test_no_data_is_unknown(): void
    {
        $this->assertSame(OpeningHours::UNKNOWN, OpeningHours::status(null, $this->at('2026-09-23 12:00')));
        $this->assertSame(OpeningHours::UNKNOWN, OpeningHours::status(['open_now' => null], $this->at('2026-09-23 12:00')));
    }
}
