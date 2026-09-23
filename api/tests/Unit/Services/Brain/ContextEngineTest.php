<?php

namespace Tests\Unit\Services\Brain;

use App\Jobs\RefreshWeatherCell;
use App\Services\Brain\ContextEngine;
use App\Services\Brain\WeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContextEngineTest extends TestCase
{
    private ContextEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('brain.features.context', true);
        Config::set('brain.features.weather', true);
        Http::preventStrayRequests(); // weather must never be fetched on the request path
        Bus::fake();
        $this->engine = app(ContextEngine::class);
    }

    public function test_supper_slot_across_midnight_from_utc_now(): void
    {
        // 15:30 UTC = 23:30 MYT; 17:00 UTC = 01:00 MYT next day.
        $this->assertSame('supper', $this->engine->resolve(null, null, false, false, [], Carbon::parse('2026-09-23T15:30:00Z'))->mealSlot);
        $this->assertSame('supper', $this->engine->resolve(null, null, false, false, [], Carbon::parse('2026-09-23T17:00:00Z'))->mealSlot);
        $this->assertSame('lunch', $this->engine->resolve(null, null, false, false, [], Carbon::parse('2026-09-23T04:30:00Z'))->mealSlot);
    }

    public function test_ramadan_slots_only_for_halal_preference(): void
    {
        $iftar = Carbon::parse('2027-02-15T11:00:00Z'); // 19:00 MYT, inside Ramadan 2027

        $this->assertSame('iftar', $this->engine->resolve(null, null, true, false, [], $iftar)->mealSlot);
        $this->assertSame('dinner', $this->engine->resolve(null, null, false, false, [], $iftar)->mealSlot);
    }

    public function test_month_end_skipped_when_budget_or_lens_chosen(): void
    {
        $monthEnd = Carbon::parse('2026-09-20T04:00:00Z');

        $this->assertTrue($this->engine->resolve(null, null, false, false, [], $monthEnd)->isActive('month_end'));
        $this->assertFalse($this->engine->resolve(null, null, false, true, [], $monthEnd)->isActive('month_end'));
    }

    public function test_ignored_signal_is_inactive_but_still_listed_for_the_strip(): void
    {
        $snapshot = $this->engine->resolve(null, null, false, false, ['supper'], Carbon::parse('2026-09-23T15:30:00Z'));

        $this->assertFalse($snapshot->isActive('supper'));
        $strip = collect(ContextEngine::present($snapshot))->keyBy('key');
        $this->assertTrue($strip['supper']['ignored']);
        $this->assertArrayNotHasKey('lateNightFit', ContextEngine::overlay($snapshot));
    }

    public function test_stale_weather_is_used_with_lower_confidence_and_refreshed_after_response(): void
    {
        $cell = WeatherService::cell(3.139, 101.687);
        Cache::put(WeatherService::cacheKey($cell), ['raining' => true, 'fetchedAt' => now()->subMinutes(90)->toIso8601String()], now()->addHour());

        $snapshot = $this->engine->resolve(3.139, 101.687, false, false);

        $this->assertTrue($snapshot->isActive('rain'));
        $this->assertEqualsWithDelta(0.5, $snapshot->confidence('rain'), 0.02);
        $this->assertEqualsWithDelta(5.0, ContextEngine::overlay($snapshot)['distance'], 0.2); // 10 × 0.5
        Bus::assertDispatchedAfterResponse(RefreshWeatherCell::class);
    }

    public function test_missing_weather_means_no_rain_and_a_background_refresh(): void
    {
        $snapshot = $this->engine->resolve(3.139, 101.687, false, false);

        $this->assertFalse($snapshot->isActive('rain'));
        Bus::assertDispatchedAfterResponse(RefreshWeatherCell::class);
    }

    public function test_refresh_job_parses_open_meteo(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['current' => ['precipitation' => 1.4, 'weather_code' => 61]])]);
        $cell = WeatherService::cell(3.139, 101.687);

        app(WeatherService::class)->refresh($cell);

        $this->assertTrue(Cache::get(WeatherService::cacheKey($cell))['raining']);
    }
}
