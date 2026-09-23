<?php

namespace App\Http\Controllers;

use App\Models\DecisionRecommendation;
use App\Models\MarketingEvent;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Services\Halal\HalalPresenter;
use App\Support\BotUserAgent;
use App\Support\OpeningHours;
use App\Support\RecommendationHeadline;
use App\Support\ShareLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The page behind a shared place link — works for someone without the app, and is the
 * universal-link target that opens the app for someone who has it.
 *
 * Shows MakanApa's own view of the place only: no Google ratings, reviews or photos (Places
 * terms allow those only alongside Google attribution in the app). Halal wording is
 * HalalPresenter's, verbatim — never shortened to "Halal" for a friendlier preview.
 */
class SharePlaceController extends Controller
{
    private const PICKER_WINDOW_DAYS = 30;

    private const MIN_PICKERS_SHOWN = 2;

    public function __construct(private readonly HalalPresenter $halalPresenter) {}

    public function show(Request $request, string $place): Response|RedirectResponse
    {
        $restaurant = $this->resolve($place);
        if ($restaurant instanceof RedirectResponse) {
            return $restaurant;
        }
        if ($restaurant === null) {
            return response()->view('share.missing', status: 404);
        }

        $ref = $this->ref($request);
        $this->record($request, MarketingEvent::SHARE_VIEW, $restaurant, $ref);

        $restaurant->loadMissing('cuisines', 'tags', 'activeHalalCertificate');
        $data = $restaurant->toRecommendationArray();
        $halal = $this->halalPresenter->summaryFromArray($data);
        $key = ShareLinks::placeKey($restaurant->id, $restaurant->name);

        return response()->view('share.place', [
            'restaurant' => $restaurant,
            'category' => RecommendationHeadline::categoryLabel($restaurant->food_category),
            'openStatus' => $data['open_status'],
            'closesAt' => OpeningHours::closesAt($restaurant->opening_hours, now()),
            'price' => self::priceLabel($restaurant->price_level),
            'halal' => $halal['display'],
            'pickers' => $this->recentPickers($restaurant),
            'menuRange' => $this->menuPriceRange($restaurant),
            'description' => $this->previewDescription($halal['display']['shortLabel'], self::priceLabel($restaurant->price_level), $data['open_status']),
            'openAppUrl' => url("/p/{$key}/go/app").($ref ? "?ref={$ref}" : ''),
            'getAppUrl' => url("/p/{$key}/go/download").($ref ? "?ref={$ref}" : ''),
            'directionsUrl' => $this->directionsUrl($restaurant),
            'ogImage' => $this->ogImage($restaurant->food_category),
            'indexable' => (bool) config('marketing.share_indexable'),
        ]);
    }

    /**
     * "Open in MakanApa" / "Get the app" go through here so taps are counted server-side (no JS
     * tracker). Opening uses the app's own URL scheme: a universal link tapped on its own domain
     * stays in Safari, so the same /p URL can't open the app from this page.
     */
    public function go(Request $request, string $place, string $target): RedirectResponse
    {
        $restaurant = $this->resolve($place);
        if (! $restaurant instanceof Restaurant) {
            return redirect()->away((string) config('marketing.app_download_url'));
        }

        $ref = $this->ref($request);
        if ($target === 'app') {
            $this->record($request, MarketingEvent::SHARE_OPEN_APP, $restaurant, $ref);

            return redirect()->away("makanapa://place/{$restaurant->id}?source=share");
        }

        $this->record($request, MarketingEvent::SHARE_GET_APP, $restaurant, $ref);

        return redirect()->away((string) config('marketing.app_download_url'));
    }

    /** Universal links: /p/* opens the app; /g/* is reserved for Geng rooms. */
    public function appSiteAssociation(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'details' => [[
                    'appIDs' => [(string) config('marketing.apple_app_id')],
                    'components' => [
                        ['/' => '/p/*', 'comment' => 'Shared places'],
                        ['/' => '/g/*', 'comment' => 'Geng rooms (reserved)'],
                    ],
                ]],
            ],
        ]);
    }

    /**
     * Id is authoritative, slug cosmetic: a merged-away place or a stale/wrong slug redirects to
     * the canonical URL (keeping ?ref=), an inactive or unknown one resolves to null.
     */
    private function resolve(string $place): Restaurant|RedirectResponse|null
    {
        $id = (int) strtok($place, '-');
        $restaurant = $id > 0 ? Restaurant::find($id) : null;
        if ($restaurant === null) {
            return null;
        }

        $canonical = $restaurant->canonicalRestaurant();
        if (! $canonical->is_active) {
            return null;
        }

        $key = ShareLinks::placeKey($canonical->id, $canonical->name);
        if ($canonical->id !== $restaurant->id || $place !== $key) {
            $query = request()->getQueryString();
            $suffix = request()->route('target') ? '/go/'.request()->route('target') : '';

            return redirect("/p/{$key}{$suffix}".($query ? "?{$query}" : ''), 301);
        }

        return $canonical;
    }

    private function ref(Request $request): ?string
    {
        $ref = $request->query('ref');

        return in_array($ref, ShareLinks::REFS, true) ? $ref : null;
    }

    private function record(Request $request, string $event, Restaurant $restaurant, ?string $ref): void
    {
        if (BotUserAgent::matches($request->userAgent())) {
            return;
        }

        MarketingEvent::create(['event' => $event, 'source' => $ref, 'restaurant_id' => $restaurant->id]);
    }

    private function recentPickers(Restaurant $restaurant): ?int
    {
        $pickers = (int) DecisionRecommendation::query()
            ->join('decisions', 'decisions.id', '=', 'decision_recommendations.decision_id')
            ->where('decision_recommendations.restaurant_id', $restaurant->id)
            ->whereNotNull('decision_recommendations.accepted_at')
            ->where('decision_recommendations.accepted_at', '>=', now()->subDays(self::PICKER_WINDOW_DAYS))
            ->whereNotNull('decisions.user_id')
            ->count(DB::raw('distinct decisions.user_id'));

        return $pickers >= self::MIN_PICKERS_SHOWN ? $pickers : null;
    }

    private function menuPriceRange(Restaurant $restaurant): ?string
    {
        $prices = RestaurantMenuItem::where('restaurant_id', $restaurant->id)->whereNotNull('price')->pluck('price')->map(fn ($p) => (float) $p);
        if ($prices->isEmpty()) {
            return null;
        }
        $format = fn (float $price) => 'RM'.rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');

        return $prices->min() === $prices->max() ? $format($prices->min()) : $format($prices->min()).'–'.$format($prices->max());
    }

    /** Same vocabulary as the app: HalalPresenter's short label, price band, open state. */
    private function previewDescription(string $halalLabel, ?string $price, string $openStatus): string
    {
        return collect([
            $halalLabel,
            $price,
            match ($openStatus) {
                'open' => 'Open now',
                'closed' => 'Closed now',
                default => null,
            },
        ])->filter()->implode(' · ').' — picked on MakanApa';
    }

    private function directionsUrl(Restaurant $restaurant): string
    {
        $query = http_build_query(array_filter([
            'api' => 1,
            'query' => "{$restaurant->latitude},{$restaurant->longitude}",
            'query_place_id' => $restaurant->provider === 'google' ? $restaurant->provider_place_id : null,
        ]));

        return "https://www.google.com/maps/search/?{$query}";
    }

    private function ogImage(?string $foodCategory): string
    {
        $categoryImage = $foodCategory ? "images/share/{$foodCategory}.png" : null;

        return asset($categoryImage && file_exists(public_path($categoryImage)) ? $categoryImage : 'images/share/default.png');
    }

    /** Mirrors the app's PricePresentation so the page and the app say the same thing. */
    public static function priceLabel(?int $priceLevel): ?string
    {
        return match ($priceLevel) {
            1 => '≈ RM10/person',
            2 => '≈ RM20/person',
            3 => '≈ RM35+/person',
            default => null,
        };
    }
}
