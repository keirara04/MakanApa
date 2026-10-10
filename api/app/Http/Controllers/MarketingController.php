<?php

namespace App\Http\Controllers;

use App\Models\MarketingEvent;
use App\Services\Marketing\LandingInsights;
use App\Support\BotUserAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketingController extends Controller
{
    /** The moods the landing page's "try it" demo offers — the same tags the app sends. */
    public const DEMO_MOODS = ['nasi_kandar', 'nasi_lemak', 'ayam_gepuk', 'mee_goreng', 'char_kuey_teow'];

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
     * The landing page's "try it": one real pick around the demo campus, for the page script. The
     * three answers mirror the app's questions; `exclude` is what "Cari lagi" has already shown.
     */
    public function tryPick(Request $request, LandingInsights $insights): JsonResponse
    {
        $data = $request->validate([
            'mood' => ['nullable', Rule::in(self::DEMO_MOODS)],
            'budget' => ['nullable', 'integer', 'between:1,3'],
            'km' => ['required', Rule::in(['1', '2', '5'])],
            'exclude' => ['nullable', 'array', 'max:20'],
            'exclude.*' => ['integer'],
        ]);

        $pick = $insights->demoPick(
            $data['mood'] ?? null,
            isset($data['budget']) ? (int) $data['budget'] : null,
            (float) $data['km'],
            array_map('intval', $data['exclude'] ?? []),
        );

        return response()->json(['near' => $insights->anchor()['label'], 'pick' => $pick]);
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
