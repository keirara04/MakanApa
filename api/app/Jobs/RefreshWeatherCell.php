<?php

namespace App\Jobs;

use App\Services\Brain\WeatherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Runs after the response is sent (dispatchAfterResponse) — never adds latency to a pick. */
class RefreshWeatherCell implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $cell) {}

    public function handle(WeatherService $weather): void
    {
        try {
            $weather->refresh($this->cell);
        } catch (Throwable) {
            // Weather is a nice-to-have nudge; a failed refresh just means no rain signal.
        }
    }
}
