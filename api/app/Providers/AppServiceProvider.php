<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
