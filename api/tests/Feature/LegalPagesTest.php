<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_terms_include_the_clauses_app_review_and_google_require(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('zero tolerance for objectionable content and abusive users')
            // Google Maps Platform Terms 3.2.2(a)(i): end-user terms must bind users to these two.
            ->assertSee('https://maps.google.com/help/terms_maps/', false)
            ->assertSee('https://policies.google.com/privacy', false)
            ->assertSee(config('legal.operator_name'))
            ->assertSee('Last updated: '.Carbon::parse(config('legal.terms_version'))->format('j F Y'));
    }

    public function test_community_guidelines_state_zero_tolerance_and_how_to_report(): void
    {
        $this->get('/community-guidelines')
            ->assertOk()
            ->assertSee('zero tolerance for objectionable content and abusive users')
            ->assertSee('We review reports within 24 hours.');
    }

    public function test_privacy_policy_is_available_in_english_and_bahasa_malaysia(): void
    {
        // PDPA s.7(3): the notice must be in both the national language and English.
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('<div lang="en"', false)
            ->assertSee('Personal Data Protection Act 2010')
            ->assertSee(route('privacy', ['lang' => 'ms']), false);

        $this->get('/privacy?lang=ms')
            ->assertOk()
            ->assertSee('<div lang="ms"', false)
            ->assertSee('Akta Perlindungan Data Peribadi 2010')
            ->assertSee('Notis Privasi');
    }

    public function test_privacy_policy_discloses_that_pick_locations_are_stored(): void
    {
        $this->get('/privacy')
            ->assertSee('We store your exact location with each')
            ->assertSee('OpenRouter')
            ->assertSee('Open-Meteo');
    }

    public function test_layout_links_every_legal_page(): void
    {
        $this->get('/')
            ->assertSee('href="'.route('terms').'"', false)
            ->assertSee('href="'.route('privacy').'"', false)
            ->assertSee('href="'.route('community-guidelines').'"', false);
    }

    public function test_legal_pages_set_no_session_or_csrf_cookies(): void
    {
        foreach (['/terms', '/community-guidelines', '/privacy?lang=ms'] as $path) {
            $response = $this->get($path);

            $response->assertCookieMissing('XSRF-TOKEN');
            $response->assertCookieMissing(config('session.cookie'));
        }
    }
}
