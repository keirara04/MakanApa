<?php

namespace Tests\Feature;

use App\Models\MarketingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    public function test_home_download_buttons_go_through_tracked_redirect(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['nav', 'hero', 'final'] as $from) {
            $response->assertSee('href="'.route('marketing.download', ['from' => $from]).'"', false);
        }
    }

    public function test_download_redirects_to_configured_url_and_counts_click(): void
    {
        config(['marketing.app_download_url' => 'https://testflight.apple.com/join/test123']);

        $this->withHeader('User-Agent', self::BROWSER)
            ->get('/go/testflight?from=hero')
            ->assertRedirect('https://testflight.apple.com/join/test123');

        $this->assertDatabaseHas('marketing_events', ['event' => MarketingEvent::TESTFLIGHT_CLICK, 'source' => 'hero']);
    }

    public function test_download_with_unknown_source_is_stored_without_one(): void
    {
        $this->withHeader('User-Agent', self::BROWSER)->get('/go/testflight?from=<script>');

        $this->assertDatabaseHas('marketing_events', ['event' => MarketingEvent::TESTFLIGHT_CLICK, 'source' => null]);
    }

    public function test_landing_view_is_counted_for_browsers_but_not_link_previews(): void
    {
        // Deltas, not absolutes: tests without RefreshDatabase (e.g. ExampleTest's GET /) can
        // leave committed rows behind in the shared local DB.
        $views = fn () => MarketingEvent::where('event', MarketingEvent::LANDING_VIEW)->count();
        $clicks = fn () => MarketingEvent::where('event', MarketingEvent::TESTFLIGHT_CLICK)->count();
        [$viewsBefore, $clicksBefore] = [$views(), $clicks()];

        $this->withHeader('User-Agent', self::BROWSER)->get('/');
        $this->withHeader('User-Agent', 'WhatsApp/2.24.1 A')->get('/');
        $this->withHeader('User-Agent', 'WhatsApp/2.24.1 A')->get('/go/testflight?from=hero')->assertRedirect();

        $this->assertSame($viewsBefore + 1, $views());
        $this->assertSame($clicksBefore, $clicks());
    }

    public function test_home_has_link_preview_metadata(): void
    {
        $this->get('/')
            ->assertSee('<meta property="og:image" content="'.asset('images/og.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_every_image_on_home_has_alt_text(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/<img\b[^>]*>/', $html, $images);

        $this->assertNotEmpty($images[0]);
        foreach ($images[0] as $img) {
            $this->assertMatchesRegularExpression('/\balt="/', $img, "Image missing alt: {$img}");
        }
    }

    public function test_home_does_not_advertise_unreleased_features_as_available(): void
    {
        // Geng mode is "coming soon" in the app; the page must say so on its card.
        $this->get('/')->assertSeeInOrder(['Geng mode', 'Coming soon', 'Everyone votes']);
    }

    public function test_marketing_pages_set_no_session_or_csrf_cookies(): void
    {
        foreach (['/', '/support', '/privacy', '/go/testflight'] as $path) {
            $response = $this->get($path);

            $response->assertCookieMissing('XSRF-TOKEN');
            $response->assertCookieMissing(config('session.cookie'));
        }
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_hsts_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_api_responses_also_carry_security_headers(): void
    {
        $this->getJson('/up')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
