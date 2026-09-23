<?php

namespace Tests\Feature;

use App\Models\MarketingEvent;
use App\Models\Restaurant;
use App\Support\Halal\HalalStatus;
use App\Support\ShareLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SharePlaceTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

    private function makeRestaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'KFC Jalan Reko', 'latitude' => 2.9284, 'longitude' => 101.7802, 'is_active' => true,
            'provider' => 'google', 'provider_place_id' => 'ChIJ-kfc', 'address' => 'Jalan Reko, Kajang',
            'food_category' => 'fast_food', 'price_level' => 2, 'rating' => 3.9, 'user_rating_count' => 812,
        ], $overrides));
    }

    private function visit(string $path): TestResponse
    {
        return $this->withHeader('User-Agent', self::BROWSER)->get($path);
    }

    public function test_share_page_shows_the_place_with_link_preview_tags(): void
    {
        $restaurant = $this->makeRestaurant();

        $response = $this->visit('/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name).'?ref=share')->assertOk();

        $response->assertSee('KFC Jalan Reko')
            ->assertSee('Jalan Reko, Kajang')
            ->assertSee('property="og:title"', false)
            ->assertSee('images/share/', false)
            ->assertSee('/go/app?ref=share', false)
            ->assertSee('name="robots" content="noindex"', false);
    }

    public function test_share_page_never_shows_google_ratings(): void
    {
        $restaurant = $this->makeRestaurant();

        $this->visit('/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name))->assertOk()
            ->assertDontSee('3.9')
            ->assertDontSee('812');
    }

    public function test_halal_wording_is_never_simplified(): void
    {
        $unknown = $this->makeRestaurant();
        $friendly = $this->makeRestaurant(['name' => 'Kedai Friendly', 'halal_status' => HalalStatus::MuslimFriendly]);

        $this->visit('/p/'.ShareLinks::placeKey($unknown->id, $unknown->name))->assertOk()
            ->assertSee('Not verified · Help verify')
            ->assertDontSee('>Halal<', false);
        $this->visit('/p/'.ShareLinks::placeKey($friendly->id, $friendly->name))->assertOk()
            ->assertSee('Muslim-friendly')
            ->assertDontSee('>Halal<', false);
    }

    public function test_wrong_slug_redirects_to_the_canonical_url_keeping_the_ref(): void
    {
        $restaurant = $this->makeRestaurant();

        $this->visit("/p/{$restaurant->id}-old-name?ref=share")
            ->assertRedirect('/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name).'?ref=share')
            ->assertStatus(301);
    }

    public function test_merged_place_redirects_to_the_place_it_was_merged_into(): void
    {
        $canonical = $this->makeRestaurant(['name' => 'KFC Kajang']);
        $merged = $this->makeRestaurant(['name' => 'KFC Kajang Dup', 'provider_place_id' => 'ChIJ-dup', 'is_active' => false, 'merged_into_restaurant_id' => $canonical->id]);

        $this->visit('/p/'.ShareLinks::placeKey($merged->id, $merged->name))
            ->assertRedirect('/p/'.ShareLinks::placeKey($canonical->id, $canonical->name));
    }

    public function test_inactive_or_unknown_place_shows_a_friendly_404(): void
    {
        $closed = $this->makeRestaurant(['is_active' => false]);

        $this->visit('/p/'.ShareLinks::placeKey($closed->id, $closed->name))->assertNotFound()->assertSee('Get the app');
        $this->visit('/p/999999')->assertNotFound();
    }

    public function test_share_funnel_is_counted_per_place_and_ref_but_not_for_link_preview_bots(): void
    {
        $restaurant = $this->makeRestaurant();
        $key = ShareLinks::placeKey($restaurant->id, $restaurant->name);

        $this->postJson("/api/v1/restaurants/{$restaurant->id}/share-events")->assertCreated();
        $this->withHeader('User-Agent', 'WhatsApp/2.24')->get("/p/{$key}?ref=share")->assertOk();
        $this->visit("/p/{$key}?ref=share")->assertOk();
        $this->visit("/p/{$key}/go/app?ref=share")->assertRedirect("makanapa://place/{$restaurant->id}?source=share");
        $this->visit("/p/{$key}/go/download?ref=share")->assertRedirect(config('marketing.app_download_url'));

        $events = MarketingEvent::where('restaurant_id', $restaurant->id)->orderBy('id')->get(['event', 'source']);
        $this->assertSame(
            [MarketingEvent::SHARE_STARTED, MarketingEvent::SHARE_VIEW, MarketingEvent::SHARE_OPEN_APP, MarketingEvent::SHARE_GET_APP],
            $events->pluck('event')->all()
        );
        $this->assertSame(['share'], $events->pluck('source')->unique()->values()->all());
    }

    public function test_share_url_comes_from_the_api(): void
    {
        $restaurant = $this->makeRestaurant();

        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->assertOk()
            ->assertJsonPath('shareUrl', ShareLinks::place($restaurant->id, $restaurant->name))
            ->assertJsonPath('latitude', 2.9284);
        $this->getJson('/api/v1/places/search?query=kfc&latitude=2.9284&longitude=101.7802')->assertOk()
            ->assertJsonPath('results.0.shareUrl', ShareLinks::place($restaurant->id, $restaurant->name));
    }

    public function test_apple_app_site_association_lists_the_app_and_paths(): void
    {
        $this->get('/.well-known/apple-app-site-association')->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('applinks.details.0.appIDs.0', config('marketing.apple_app_id'))
            ->assertJsonPath('applinks.details.0.components.0./', '/p/*');
    }
}
