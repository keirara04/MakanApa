<?php

namespace App\Http\Controllers;

use App\Models\MarketingEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingController extends Controller
{
    /** Link-preview fetchers and scripts shouldn't inflate the funnel. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless/i';

    public function home(Request $request): View
    {
        if (! $this->isBot($request)) {
            MarketingEvent::create(['event' => MarketingEvent::LANDING_VIEW]);
        }

        return view('home');
    }

    /**
     * Every "Get the app" button points here instead of straight at TestFlight/App Store, so
     * clicks can be counted per placement without any client-side analytics.
     */
    public function download(Request $request): RedirectResponse
    {
        if (! $this->isBot($request)) {
            $source = $request->query('from');

            MarketingEvent::create([
                'event' => MarketingEvent::TESTFLIGHT_CLICK,
                'source' => in_array($source, MarketingEvent::SOURCES, true) ? $source : null,
            ]);
        }

        return redirect()->away(config('marketing.app_download_url'));
    }

    private function isBot(Request $request): bool
    {
        return (bool) preg_match(self::BOT_PATTERN, (string) $request->userAgent());
    }
}
