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
 * and its best recent written reviews (the public reviews RSS feed) into the cache for the
 * landing page. The page only ever reads the cache, so a slow or failing
 * Apple endpoint never touches a page render. On failure the last good value is kept until it
 * expires on its own, so a long outage hides the rating instead of showing a stale one forever.
 */
#[Signature('marketing:refresh-app-rating')]
#[Description('Cache the App Store rating and reviews shown on the landing page')]
class RefreshAppStoreRating extends Command
{
    private const KEEP_FOR_DAYS = 7;

    /** Most reviews kept for the page, newest first. */
    private const MAX_REVIEWS = 6;

    public function handle(): int
    {
        $appId = Config::get('marketing.app_store_id');
        if (! $appId) {
            $this->info('No APP_STORE_ID configured; nothing to refresh.');

            return self::SUCCESS;
        }

        // Reviews are a separate request: whatever happens to them never changes the rating's result.
        $this->refreshReviews((string) $appId);

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

    /**
     * Keeps only 4 and 5 star reviews of a readable length. An empty feed caches an empty list,
     * so a review Apple took down disappears from the page too. A failed request keeps the last
     * good list until it expires on its own.
     */
    private function refreshReviews(string $appId): void
    {
        try {
            $entries = Http::timeout(5)
                ->get("https://itunes.apple.com/my/rss/customerreviews/page=1/id={$appId}/sortby=mostrecent/json")
                ->throw()
                ->json('feed.entry') ?? [];
        } catch (Throwable $e) {
            $this->warn("App Store reviews failed: {$e->getMessage()}");

            return;
        }

        // A feed with a single review gives the entry itself, not a list of one.
        if (isset($entries['id'])) {
            $entries = [$entries];
        }

        $reviews = collect(is_array($entries) ? $entries : [])
            ->map(fn ($entry) => is_array($entry) ? [
                'id' => (string) data_get($entry, 'id.label'),
                'rating' => (int) data_get($entry, 'im:rating.label'),
                'title' => trim((string) data_get($entry, 'title.label')),
                'body' => trim((string) data_get($entry, 'content.label')),
                'author' => trim((string) data_get($entry, 'author.name.label')),
            ] : null)
            ->filter(fn (?array $review) => $review !== null
                && $review['rating'] >= 4
                && $review['author'] !== ''
                && mb_strlen($review['body']) >= 30
                && mb_strlen($review['body']) <= 320)
            ->take(self::MAX_REVIEWS)
            ->values()
            ->all();

        Cache::put(LandingInsights::APP_REVIEWS_CACHE_KEY, $reviews, now()->addDays(self::KEEP_FOR_DAYS));

        $this->info('App Store reviews kept: '.count($reviews).'.');
    }
}
