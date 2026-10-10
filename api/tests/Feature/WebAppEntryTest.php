<?php

namespace Tests\Feature;

use App\Models\MarketingEvent;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAppEntryTest extends TestCase
{
    use RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private const DESKTOP = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private const GOOGLEBOT_PHONE = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketing.web_app_url' => 'https://app.trymakanapa.com']);
    }

    public function test_until_live_android_visitors_still_get_the_app_store(): void
    {
        config(['marketing.web_app_live' => false]);

        $this->withHeader('User-Agent', self::ANDROID)->get('/')
            ->assertOk()
            ->assertDontSee('/go/web-app', false)
            ->assertSee('Not yet. MakanApa is iPhone only for now.');
    }

    public function test_once_live_android_visitors_open_the_web_app(): void
    {
        config(['marketing.web_app_live' => true]);

        $this->withHeader('User-Agent', self::ANDROID)->get('/')
            ->assertOk()
            ->assertHeader('Vary', 'User-Agent')
            ->assertSee('Open MakanApa')
            ->assertSee('/go/web-app?from=nav', false)
            ->assertSee('/go/web-app?from=hero', false)
            ->assertDontSee('alt="Download on the App Store"', false)
            ->assertSee('Yes. On Android, MakanApa runs in Chrome');
    }

    public function test_iphones_keep_the_app_store_and_computers_also_get_the_browser(): void
    {
        config(['marketing.web_app_live' => true]);

        $this->withHeader('User-Agent', self::IPHONE)->get('/')
            ->assertSee('alt="Download on the App Store"', false)
            ->assertDontSee('Or use it in your browser');

        $this->withHeader('User-Agent', self::DESKTOP)->get('/')
            ->assertSee('alt="Download on the App Store"', false)
            ->assertSee('Or use it in your browser');
    }

    public function test_search_engines_index_the_app_store_page(): void
    {
        config(['marketing.web_app_live' => true]);

        $this->withHeader('User-Agent', self::GOOGLEBOT_PHONE)->get('/')
            ->assertSee('alt="Download on the App Store"', false)
            ->assertDontSee('Open MakanApa');
    }

    public function test_opening_the_web_app_is_counted_per_placement(): void
    {
        $this->withHeader('User-Agent', self::ANDROID)->get('/go/web-app?from=hero')
            ->assertRedirect('https://app.trymakanapa.com');

        $this->assertDatabaseHas('marketing_events', ['event' => MarketingEvent::WEB_APP_CLICK, 'source' => 'hero']);
    }

    public function test_a_dead_shared_link_offers_the_web_app_on_android(): void
    {
        config(['marketing.web_app_live' => true]);

        $this->withHeader('User-Agent', self::ANDROID)->get('/p/999999-gone')
            ->assertNotFound()
            ->assertSee('/go/web-app?from=missing', false)
            ->assertDontSee('alt="Download on the App Store"', false);
    }

    public function test_a_shared_place_opens_in_the_web_app_on_android(): void
    {
        config(['marketing.web_app_live' => true]);
        $place = Restaurant::create([
            'name' => 'Nasi Kandar Pelita', 'latitude' => 2.9284, 'longitude' => 101.7802, 'is_active' => true,
            'provider' => 'google', 'provider_place_id' => 'ChIJ-pelita', 'food_category' => 'mamak', 'price_level' => 1,
        ]);

        $this->withHeader('User-Agent', self::ANDROID)->get("/p/{$place->id}-nasi-kandar-pelita")
            ->assertOk()
            ->assertSee('Open in MakanApa')
            ->assertSee("/p/{$place->id}-nasi-kandar-pelita/go/web", false)
            ->assertDontSee('Open this place in the app');

        $this->withHeader('User-Agent', self::ANDROID)->get("/p/{$place->id}-nasi-kandar-pelita/go/web")
            ->assertRedirect("https://app.trymakanapa.com/p/{$place->id}-nasi-kandar-pelita");
        $this->assertDatabaseHas('marketing_events', ['event' => MarketingEvent::SHARE_OPEN_APP, 'restaurant_id' => $place->id]);
    }
}
