<?php

namespace App\Providers;

use App\Services\Craving\CravingResolver;
use App\Services\Craving\DailyAiBudget;
use App\Services\Craving\OpenRouterIntentParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // Keyed on normalized email + IP (tight, per-account) plus a looser IP-wide ceiling,
        // so shared campus Wi-Fi/NAT can't let one student's bad attempts lock out others.
        RateLimiter::for('login', function ($request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('social', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // auth/link is a password-check endpoint (it's how an existing account proves ownership
        // before a social identity gets attached) — throttled at least as tightly as login, plus
        // a per-token cap so a leaked/guessed linkToken can't be brute-forced across IPs.
        RateLimiter::for('link', function ($request) {
            $token = (string) $request->input('linkToken');

            return [
                Limit::perMinute(5)->by($token),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });
    }
}
