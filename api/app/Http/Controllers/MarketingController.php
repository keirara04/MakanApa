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
            'nearby' => $insights->nearbyList(),
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
        return BotUserAgent::matches($request->userAgent());
    }
}
