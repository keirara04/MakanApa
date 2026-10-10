<?php

namespace App\Http\Controllers;

use App\Models\MarketingEvent;
use App\Services\Marketing\LandingInsights;
use App\Support\BotUserAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function home(Request $request, LandingInsights $insights): View
    {
        if (! $this->isBot($request)) {
            MarketingEvent::create(['event' => MarketingEvent::LANDING_VIEW]);
        }

        return view('home', [
            'demoArea' => $insights->anchor()['label'],
            'stats' => $insights->stats(),
            'appRating' => $insights->appRating(),
            'nearby' => $insights->nearbyList(),
            'coverage' => $insights->coverage(),
            'reviews' => $insights->appReviews(),
        ]);
    }

    /**
     * The page ambassadors share (community posts can't carry links). `?campus=` names their
     * campus on the page and tags its download buttons with that campus's App Store campaign.
     */
    public function ambassadors(Request $request): View
    {
        $campuses = config('marketing.ambassador_campuses');
        $campus = $request->query('campus');

        return view('ambassadors', [
            'campuses' => $campuses,
            'campus' => is_string($campus) && isset($campuses[$campus]) ? $campus : null,
        ]);
    }

    /**
     * Every "Get the app" button points here instead of straight at the App Store, so
     * clicks can be counted per placement without any client-side analytics.
     */
    public function download(Request $request): RedirectResponse
    {
        if (! $this->isBot($request)) {
            $source = $request->query('from');

            MarketingEvent::create([
                'event' => MarketingEvent::APP_STORE_CLICK,
                'source' => in_array($source, MarketingEvent::SOURCES, true) ? $source : null,
            ]);
        }

        return redirect()->away($this->downloadUrl($request->query('campus')));
    }

    /**
     * The plain listing, or for a campus with live ambassadors the same listing as an App Store
     * campaign link (pt/ct), so installs from that campus show up in App Analytics.
     */
    private function downloadUrl(mixed $campus): string
    {
        $storeId = config('marketing.app_store_id');
        $providerToken = config('marketing.app_store_provider_token');

        if (! is_string($campus) || ! isset(config('marketing.ambassador_campuses')[$campus]) || ! $storeId || ! $providerToken) {
            return config('marketing.app_download_url');
        }

        return 'https://apps.apple.com/app/apple-store/id'.$storeId.'?'.http_build_query([
            'pt' => $providerToken,
            'ct' => 'amb_'.$campus,
            'mt' => 8,
        ]);
    }

    private function isBot(Request $request): bool
    {
        return BotUserAgent::matches($request->userAgent());
    }
}
