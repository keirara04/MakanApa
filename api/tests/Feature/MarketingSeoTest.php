<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\Marketing\LandingInsights;
use App\Support\MarketingUrl;
use App\Support\ShareLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Warung Kak Ros', 'latitude' => 2.9284, 'longitude' => 101.7802, 'is_active' => true,
            'provider' => 'user_submitted', 'address' => 'Jalan Reko, Kajang', 'food_category' => 'malay', 'price_level' => 1,
        ], $overrides));
    }

    private function placePath(Restaurant $restaurant): string
    {
        return '/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name);
    }

    public function test_other_hosts_point_their_canonical_at_the_marketing_domain_and_are_noindexed(): void
    {
        config(['marketing.domain' => 'makanapa.test']);

        $this->get('http://api-dev.test/support')
            ->assertSee('<link rel="canonical" href="https://makanapa.test/support">', false)
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->get('http://makanapa.test/support')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_canonical_leaves_out_tracking_query_strings(): void
    {
        $restaurant = $this->makeRestaurant();

        $this->get($this->placePath($restaurant).'?ref=share')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url($this->placePath($restaurant)).'">', false);
    }

    public function test_malay_privacy_notice_is_its_own_canonical_page(): void
    {
        $this->get('/privacy?lang=ms')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/privacy').'?lang=ms">', false)
            ->assertSee('<meta property="og:locale" content="ms_MY">', false)
            ->assertSee('hreflang="x-default" href="'.url('/privacy').'"', false);
    }

    public function test_sitemap_lists_the_pages_and_only_community_added_places(): void
    {
        $community = $this->makeRestaurant();
        $google = $this->makeRestaurant(['name' => 'KFC Jalan Reko', 'provider' => 'google', 'provider_place_id' => 'ChIJ-kfc']);
        $closed = $this->makeRestaurant(['name' => 'Kedai Tutup', 'is_active' => false]);
        $merged = $this->makeRestaurant(['name' => 'Warung Kak Ros Dup', 'is_active' => false, 'merged_into_restaurant_id' => $community->id]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $response->assertSee('<loc>'.MarketingUrl::to('/').'</loc>', false)
            ->assertSee('<loc>'.MarketingUrl::to('/ambassadors').'</loc>', false)
            ->assertSee('<loc>'.url('/privacy').'?lang=ms</loc>', false)
            ->assertSee('<lastmod>'.config('legal.terms_version').'</lastmod>', false)
            ->assertSee('<loc>'.url($this->placePath($community)).'</loc>', false);
        foreach ([$google, $closed, $merged] as $excluded) {
            $response->assertDontSee($this->placePath($excluded), false);
        }
    }

    public function test_sitemap_leaves_out_places_when_share_pages_are_not_indexable(): void
    {
        config(['marketing.share_indexable' => false]);
        $community = $this->makeRestaurant();

        $this->get('/sitemap.xml')->assertOk()->assertDontSee($this->placePath($community), false);
    }

    public function test_robots_txt_points_at_the_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /go/')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_only_community_added_places_are_indexable_and_described_as_restaurants(): void
    {
        $community = $this->makeRestaurant();
        $google = $this->makeRestaurant(['name' => 'KFC Jalan Reko', 'provider' => 'google', 'provider_place_id' => 'ChIJ-kfc', 'rating' => 3.9]);

        $html = $this->get($this->placePath($community))->assertOk()
            ->assertDontSee('name="robots" content="noindex"', false)
            ->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $data = json_decode($match[1] ?? '', true);
        $this->assertSame('Restaurant', $data['@type']);
        $this->assertSame('Warung Kak Ros', $data['name']);
        $this->assertSame('MY', $data['address']['addressCountry']);
        $this->assertArrayNotHasKey('aggregateRating', $data);
        $this->assertStringContainsString('/og/p/'.$community->id.'/', $data['image']);

        $this->get($this->placePath($google))->assertOk()
            ->assertSee('name="robots" content="noindex"', false)
            ->assertDontSee('application/ld+json', false);
    }

    public function test_app_store_rating_shows_on_the_page_and_in_structured_data_only_above_the_floor(): void
    {
        config(['marketing.stats.min.app_ratings' => 10]);

        Cache::put(LandingInsights::APP_RATING_CACHE_KEY, ['rating' => 4.8, 'count' => 25]);
        $this->get('/')->assertSee('★ 4.8', false)->assertSee('"aggregateRating"', false);

        Cache::put(LandingInsights::APP_RATING_CACHE_KEY, ['rating' => 5.0, 'count' => 3]);
        $this->get('/')->assertDontSee('★ 5.0', false)->assertDontSee('"aggregateRating"', false);
    }

    public function test_refresh_command_caches_the_app_store_rating(): void
    {
        Http::fake(['itunes.apple.com/*' => Http::response(['resultCount' => 1, 'results' => [['averageUserRating' => 4.76, 'userRatingCount' => 31]]])]);

        $this->artisan('marketing:refresh-app-rating')->assertSuccessful();

        $this->assertSame(['rating' => 4.8, 'count' => 31], Cache::get(LandingInsights::APP_RATING_CACHE_KEY));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'id='.config('marketing.app_store_id')) && str_contains($request->url(), 'country=my'));
    }

    public function test_refresh_command_keeps_the_last_rating_when_apple_fails(): void
    {
        Cache::put(LandingInsights::APP_RATING_CACHE_KEY, ['rating' => 4.5, 'count' => 20]);
        Http::fake(['itunes.apple.com/*' => Http::response('', 503)]);

        $this->artisan('marketing:refresh-app-rating')->assertFailed();

        $this->assertSame(['rating' => 4.5, 'count' => 20], Cache::get(LandingInsights::APP_RATING_CACHE_KEY));
    }

    public function test_refresh_command_keeps_only_good_readable_reviews(): void
    {
        $entry = fn (string $id, int $rating, string $body) => [
            'id' => ['label' => $id], 'im:rating' => ['label' => (string) $rating],
            'title' => ['label' => 'Title '.$id], 'content' => ['label' => $body], 'author' => ['name' => ['label' => 'Reviewer '.$id]],
        ];
        Http::fake([
            'itunes.apple.com/my/rss/*' => Http::response(['feed' => ['entry' => [
                $entry('1', 5, 'Finally stopped arguing with my housemates about dinner.'),
                $entry('2', 2, 'Did not find anything near my place at all, sadly.'),
                $entry('3', 4, 'Too short'),
            ]]]),
            'itunes.apple.com/*' => Http::response(['resultCount' => 1, 'results' => [['averageUserRating' => 4.8, 'userRatingCount' => 12]]]),
        ]);

        $this->artisan('marketing:refresh-app-rating')->assertSuccessful();

        $this->assertSame(['1'], array_column(Cache::get(LandingInsights::APP_REVIEWS_CACHE_KEY), 'id'));
    }

    public function test_refresh_command_reads_a_feed_with_a_single_review(): void
    {
        Http::fake([
            'itunes.apple.com/my/rss/*' => Http::response(['feed' => ['entry' => [
                'id' => ['label' => '9'], 'im:rating' => ['label' => '5'], 'title' => ['label' => 'Love it'],
                'content' => ['label' => 'Picks a place in seconds and it is usually right.'], 'author' => ['name' => ['label' => 'Amir']],
            ]]]),
            'itunes.apple.com/*' => Http::response(['resultCount' => 1, 'results' => [['averageUserRating' => 5, 'userRatingCount' => 1]]]),
        ]);

        $this->artisan('marketing:refresh-app-rating')->assertSuccessful();

        $this->assertSame('Amir', Cache::get(LandingInsights::APP_REVIEWS_CACHE_KEY)[0]['author']);
    }

    public function test_reviews_show_only_once_there_are_enough(): void
    {
        config(['marketing.stats.min.app_reviews' => 3]);
        $review = fn (string $id) => ['id' => $id, 'rating' => 5, 'title' => 'Great '.$id, 'body' => 'Settles lunch every single day for us.', 'author' => 'Reviewer '.$id];

        Cache::put(LandingInsights::APP_REVIEWS_CACHE_KEY, [$review('1'), $review('2')]);
        $this->get('/')->assertDontSee('What people say.');

        Cache::put(LandingInsights::APP_REVIEWS_CACHE_KEY, [$review('1'), $review('2'), $review('3'), $review('4')]);
        $this->get('/')
            ->assertSee('What people say.')
            ->assertSee('Reviewer 3, App Store')
            ->assertDontSee('Reviewer 4, App Store');
    }
}
