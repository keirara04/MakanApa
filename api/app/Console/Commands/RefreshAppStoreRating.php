<?php

namespace App\Console\Commands;

use App\Services\Marketing\LandingInsights;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daily: copies the app's Malaysian App Store rating (iTunes Search API lookup, no key needed)
 * into the cache for the landing page. The page only ever reads the cache, so a slow or failing
 * Apple endpoint never touches a page render. On failure the last good value is kept until it
 * expires on its own, so a long outage hides the rating instead of showing a stale one forever.
 */
#[Signature('marketing:refresh-app-rating')]
#[Description('Cache the App Store rating shown on the landing page')]
class RefreshAppStoreRating extends Command
{
    private const KEEP_FOR_DAYS = 7;

    public function handle(): int
    {
        $appId = Config::get('marketing.app_store_id');
        if (! $appId) {
            $this->info('No APP_STORE_ID configured; nothing to refresh.');

            return self::SUCCESS;
        }

        try {
            $result = Http::timeout(5)
                ->get('https://itunes.apple.com/lookup', ['id' => $appId, 'country' => 'my'])
                ->throw()
                ->json('results.0');
        } catch (Throwable $e) {
            $this->warn("App Store lookup failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($result) || ! isset($result['averageUserRating'], $result['userRatingCount'])) {
            $this->warn('App Store lookup returned no rating yet.');

            return self::FAILURE;
        }

        $rating = [
            'rating' => round((float) $result['averageUserRating'], 1),
            'count' => (int) $result['userRatingCount'],
        ];
        Cache::put(LandingInsights::APP_RATING_CACHE_KEY, $rating, now()->addDays(self::KEEP_FOR_DAYS));

        $this->info("App Store rating: {$rating['rating']} from {$rating['count']} ratings.");

        return self::SUCCESS;
    }
}
