<?php

namespace App\Providers;

use App\Services\Craving\CravingResolver;
use App\Services\Craving\DailyAiBudget;
use App\Services\Craving\OpenRouterIntentParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CravingResolver::class, function () {
            $apiKey = config('services.openrouter.api_key');

            // An empty key means AI is a no-op — the resolver runs taxonomy-only. No branching
            // needed anywhere else in the app for the "don't have a key yet" state.
            if (empty($apiKey)) {
                return new CravingResolver;
            }

            return new CravingResolver(
                new OpenRouterIntentParser($apiKey, config('services.openrouter.model')),
                new DailyAiBudget((int) config('services.openrouter.daily_limit')),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every log line carries the environment so staging noise is easy to filter out of
        // production logs once both environments exist.
        Log::shareContext(['environment' => config('app.env')]);
    }
}
