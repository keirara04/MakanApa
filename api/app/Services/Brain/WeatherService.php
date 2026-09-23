<?php

namespace App\Services\Brain;

use App\Jobs\RefreshWeatherCell;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Stale-while-revalidate rain lookup. The recommendation path only ever reads the cache — it
 * never waits on Open-Meteo. Fresh → used; stale → used with lower confidence and refreshed
 * after the response; missing → no rain signal this time, refreshed after the response.
 * One cache entry per ~5 km cell, so nearby users share a single upstream call.
 */
class WeatherService
{
    /** @return array{raining: bool, ageMinutes: int, confidence: float}|null */
    public function current(float $lat, float $lon): ?array
    {
        if (! Config::get('brain.features.weather')) {
            return null;
        }

        $cell = self::cell($lat, $lon);
        $cached = Cache::get(self::cacheKey($cell));
        $fresh = (int) Config::get('brain.weather.fresh_minutes', 30);
        $maxAge = (int) Config::get('brain.weather.max_age_minutes', 180);

        $age = $cached ? (int) Carbon::parse($cached['fetchedAt'])->diffInMinutes(now()) : null;

        if ($cached === null || $age >= $fresh) {
            $this->scheduleRefresh($cell);
        }

        if ($cached === null || $age >= $maxAge) {
            return null;
        }

        return [
            'raining' => (bool) $cached['raining'],
            'ageMinutes' => $age,
            'confidence' => round(max(0.0, 1 - $age / $maxAge), 2),
        ];
    }

    /** Called only from RefreshWeatherCell — never on a request path. */
    public function refresh(string $cell): void
    {
        [$lat, $lon] = array_map('floatval', explode(':', $cell));

        $response = Http::timeout((int) Config::get('brain.weather.timeout_seconds', 3))
            ->get(Config::get('brain.weather.url'), [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'precipitation,weather_code',
            ]);

        if (! $response->successful()) {
            return; // keep serving whatever (possibly stale) value we had
        }

        $precipitation = (float) $response->json('current.precipitation', 0);
        $code = (int) $response->json('current.weather_code', 0);
        // WMO codes: 51–67 drizzle/rain, 80–82 showers, 95–99 thunderstorm.
        $rainCode = ($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82) || $code >= 95;

        Cache::put(self::cacheKey($cell), [
            'raining' => $precipitation >= (float) Config::get('brain.weather.rain_mm', 0.2) || $rainCode,
            'fetchedAt' => now()->toIso8601String(),
        ], now()->addHours(6));
    }

    public static function cell(float $lat, float $lon): string
    {
        $step = (float) Config::get('brain.weather.cell_degrees', 0.05);

        return sprintf('%.2f:%.2f', round($lat / $step) * $step, round($lon / $step) * $step);
    }

    public static function cacheKey(string $cell): string
    {
        return "brain:weather:{$cell}";
    }

    private function scheduleRefresh(string $cell): void
    {
        // Cache::add is atomic — only the first request in a 5-minute window schedules a fetch.
        if (Cache::add("brain:weather:lock:{$cell}", true, now()->addMinutes(5))) {
            RefreshWeatherCell::dispatchAfterResponse($cell);
        }
    }
}
