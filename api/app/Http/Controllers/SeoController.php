<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Support\MarketingUrl;
use App\Support\ShareLinks;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * robots.txt and sitemap.xml for the marketing site. Both are built from MARKETING_DOMAIN, so the
 * same container can serve them on any host without pointing crawlers at the wrong one.
 */
class SeoController extends Controller
{
    /** One sitemap file holds at most 50,000 URLs; past this, split into a sitemap index. */
    private const MAX_PLACES = 49_000;

    private const CACHE_SECONDS = 3600;

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Tracked redirects: nothing to index, only noise in the logs.
            'Disallow: /go/',
            'Disallow: /p/*/go/',
            '',
            'Sitemap: '.MarketingUrl::to('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('marketing:sitemap:v1', self::CACHE_SECONDS, fn () => view('seo.sitemap', [
            'pages' => $this->pages(),
            'places' => $this->places(),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** @return array<int, array{loc: string, lastmod: ?string}> */
    private function pages(): array
    {
        return [
            ['loc' => MarketingUrl::to('/'), 'lastmod' => null],
            ['loc' => MarketingUrl::to('/ambassadors'), 'lastmod' => null],
            ['loc' => MarketingUrl::to('/support'), 'lastmod' => null],
            ['loc' => MarketingUrl::to('/privacy'), 'lastmod' => Config::get('legal.privacy_version')],
            ['loc' => MarketingUrl::to('/privacy').'?lang=ms', 'lastmod' => Config::get('legal.privacy_version')],
            ['loc' => MarketingUrl::to('/terms'), 'lastmod' => Config::get('legal.terms_version')],
            ['loc' => MarketingUrl::to('/community-guidelines'), 'lastmod' => Config::get('legal.guidelines_version')],
        ];
    }

    /**
     * Share pages that may be indexed: community-added places only (SharePlaceController::isIndexable).
     *
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    private function places(): array
    {
        if (! Config::get('marketing.share_indexable')) {
            return [];
        }

        return Restaurant::query()
            ->where('is_active', true)
            ->whereNull('merged_into_restaurant_id')
            ->where('provider', SharePlaceController::INDEXABLE_PROVIDER)
            ->orderBy('id')
            ->limit(self::MAX_PLACES)
            ->cursor()
            ->map(fn (Restaurant $restaurant) => [
                'loc' => MarketingUrl::to('/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name)),
                'lastmod' => $restaurant->updated_at?->toDateString(),
            ])
            ->all();
    }
}
