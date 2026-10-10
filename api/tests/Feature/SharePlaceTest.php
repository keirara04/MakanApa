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
            'food_category' => 'fast_food', 'price_level' => 2, 'rating' => 3.9, 'user_rating_count' => 97531,
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
            ->assertSee('/og/p/'.$restaurant->id.'/', false)
            ->assertSee('/go/app?ref=share', false)
            ->assertSee('/go/download?ref=share', false)
            ->assertSee('alt="Download on the App Store"', false)
            ->assertSee('name="robots" content="noindex"', false);
    }

    public function test_smart_app_banner_opens_the_shared_place_in_the_app(): void
    {
        $restaurant = $this->makeRestaurant();
        $key = ShareLinks::placeKey($restaurant->id, $restaurant->name);

        $this->visit("/p/{$key}?ref=share")->assertOk()
            ->assertSee('content="app-id='.config('marketing.app_store_id').', app-argument='.url("/p/{$key}").'"', false);
    }

    public function test_open_in_app_tries_the_app_then_falls_back_to_the_app_store(): void
    {
        $restaurant = $this->makeRestaurant();
        $key = ShareLinks::placeKey($restaurant->id, $restaurant->name);

        $this->visit("/p/{$key}/go/app?ref=share")->assertOk()
            ->assertSee('makanapa:\/\/place\/'.$restaurant->id.'?source=share', false)
            ->assertSee('href="'.url("/p/{$key}/go/download?ref=share").'"', false)
            ->assertSee('name="robots" content="noindex"', false);
    }

    public function test_share_page_never_shows_google_ratings(): void
    {
        $restaurant = $this->makeRestaurant();

        $this->visit('/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name))->assertOk()
            ->assertDontSee('3.9')
            // A count no auto-increment id will contain — the place URL carries the id (/p/1812-…).
            ->assertDontSee('97531');
    }

    public function test_halal_wording_is_never_simplified(): void
    {
        $unknown = $this->makeRestaurant();
        $friendly = $this->makeRestaurant(['name' => 'Kedai Friendly', 'halal_status' => HalalStatus::MuslimFriendly]);

        $this->visit('/p/'.ShareLinks::placeKey($unknown->id, $unknown->name))->assertOk()
            ->assertSee('Not verified · Help verify')
            ->assertDontSee('>Halal<', false);
        $this->visit('/p/'.ShareLinks::placeKey($friendly->id, $friendly->name))->assertOk()
            ->assertSee('Not certified · community notes')
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

        $this->visit('/p/'.ShareLinks::placeKey($closed->id, $closed->name))->assertNotFound()
            ->assertSee('href="'.route('marketing.download', ['from' => 'missing']).'"', false);
        $this->visit('/p/999999')->assertNotFound();
    }

    public function test_share_funnel_is_counted_per_place_and_ref_but_not_for_link_preview_bots(): void
    {
        $restaurant = $this->makeRestaurant();
        $key = ShareLinks::placeKey($restaurant->id, $restaurant->name);

        $this->postJson("/api/v1/restaurants/{$restaurant->id}/share-events")->assertCreated();
        $this->withHeader('User-Agent', 'WhatsApp/2.24')->get("/p/{$key}?ref=share")->assertOk();
        $this->visit("/p/{$key}?ref=share")->assertOk();
        $this->visit("/p/{$key}/go/app?ref=share")->assertOk();
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

    public function test_preview_card_is_drawn_per_place_and_its_url_changes_with_the_place(): void
    {
        $restaurant = $this->makeRestaurant(['name' => 'Restoran Seri Melayu Masakan Kampung Tradisional Warisan Pak Long Jasin']);
        $cardUrl = $this->previewImageUrl($restaurant);

        $response = $this->get(parse_url($cardUrl, PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/png');
        [$width, $height] = getimagesizefromstring($response->getContent());
        $this->assertSame([1200, 630], [$width, $height]);

        $restaurant->update(['name' => 'Kedai Baru']);
        $this->assertNotSame($cardUrl, $this->previewImageUrl($restaurant));
    }

    public function test_names_the_card_font_cannot_draw_keep_the_generic_preview(): void
    {
        $restaurant = $this->makeRestaurant(['name' => '老友记 Kopitiam']);

        $this->assertStringEndsWith('images/share/default.png', $this->previewImageUrl($restaurant));
    }

    private function previewImageUrl(Restaurant $restaurant): string
    {
        $html = $this->visit('/p/'.ShareLinks::placeKey($restaurant->id, $restaurant->name))->assertOk()->getContent();
        preg_match('/property="og:image" content="([^"]+)"/', $html, $match);

        return html_entity_decode($match[1]);
    }

    public function test_apple_app_site_association_lists_the_app_and_paths(): void
    {
        $this->get('/.well-known/apple-app-site-association')->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('applinks.details.0.appIDs.0', config('marketing.apple_app_id'))
            ->assertJsonPath('applinks.details.0.components.0./', '/p/*');
    }
}
