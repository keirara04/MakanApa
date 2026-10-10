<?php

namespace App\Http\Controllers;

use App\Models\DecisionRecommendation;
use App\Models\MarketingEvent;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Services\Halal\HalalPresenter;
use App\Support\BotUserAgent;
use App\Support\MarketingUrl;
use App\Support\OpeningHours;
use App\Support\RecommendationHeadline;
use App\Support\ShareCardImage;
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
    /**
     * Only places the community added may be indexed or described in structured data. Google-sourced
     * ones stay noindex: their name, address and coordinates are Places content, not ours to publish
     * to search engines.
     */
    public const INDEXABLE_PROVIDER = 'user_submitted';

    private const PICKER_WINDOW_DAYS = 30;

    private const MIN_PICKERS_SHOWN = 2;

    private const FALLBACK_CARD = 'images/share/default.png';

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
        $canonicalUrl = MarketingUrl::to("/p/{$key}");
        $price = self::priceLabel($restaurant->price_level);
        $menuRange = $this->menuPriceRange($restaurant);
        $indexable = (bool) config('marketing.share_indexable') && $restaurant->provider === self::INDEXABLE_PROVIDER;

        return response()->view('share.place', [
            'restaurant' => $restaurant,
            'category' => RecommendationHeadline::categoryLabel($restaurant->food_category),
            'openStatus' => $data['open_status'],
            'closesAt' => OpeningHours::closesAt($restaurant->opening_hours, now()),
            'price' => $price,
            'halal' => $halal['display'],
            'pickers' => $this->recentPickers($restaurant),
            'menuRange' => $menuRange,
            'description' => $this->previewDescription($halal['display']['shortLabel'], $price, $data['open_status']),
            'canonicalUrl' => $canonicalUrl,
            'openAppUrl' => url("/p/{$key}/go/app").($ref ? "?ref={$ref}" : ''),
            'getAppUrl' => url("/p/{$key}/go/download").($ref ? "?ref={$ref}" : ''),
            'directionsUrl' => $this->directionsUrl($restaurant),
            'ogImage' => $this->cardUrl($restaurant, $halal['display']),
            'indexable' => $indexable,
            'structuredData' => $indexable ? $this->structuredData($restaurant, $canonicalUrl, $menuRange ?? $price) : null,
        ]);
    }

    /**
     * "Open in MakanApa" / "Get the app" go through here so taps are counted server-side (no JS
     * tracker). Opening uses the app's own URL scheme: a universal link tapped on its own domain
     * stays in Safari, so the same /p URL can't open the app from this page. The scheme is tried
     * from a small page rather than a bare redirect, so someone without the app falls through to
     * the App Store (via the tracked download link) instead of Safari's "address is invalid".
     */
    public function go(Request $request, string $place, string $target): Response|RedirectResponse
    {
        $restaurant = $this->resolve($place);
        if (! $restaurant instanceof Restaurant) {
            return redirect()->away((string) config('marketing.app_download_url'));
        }

        $ref = $this->ref($request);
        if ($target === 'app') {
            $this->record($request, MarketingEvent::SHARE_OPEN_APP, $restaurant, $ref);
            $key = ShareLinks::placeKey($restaurant->id, $restaurant->name);
            $query = $ref ? "?ref={$ref}" : '';

            return response()->view('share.open', [
                'restaurant' => $restaurant,
                'appUrl' => "makanapa://place/{$restaurant->id}?source=share",
                'downloadUrl' => url("/p/{$key}/go/download").$query,
                'placeUrl' => url("/p/{$key}").$query,
            ]);
        }

        $this->record($request, MarketingEvent::SHARE_GET_APP, $restaurant, $ref);

        return redirect()->away((string) config('marketing.app_download_url'));
    }

    /**
     * The link-preview card for a shared place. The version in the URL is a hash of the card's
     * content, so chat apps fetch a fresh image whenever the name, price or halal status changes.
     * Anything that can't be drawn falls back to the generic image rather than failing a preview.
     */
    public function card(int $place): Response|RedirectResponse
    {
        $restaurant = Restaurant::find($place)?->canonicalRestaurant();
        if ($restaurant === null || ! $restaurant->is_active || ! ShareCardImage::supported() || ! ShareCardImage::canRender($restaurant->name)) {
            return redirect()->away(asset(self::FALLBACK_CARD));
        }

        $restaurant->loadMissing('cuisines', 'tags', 'activeHalalCertificate');
        $halal = $this->halalPresenter->summaryFromArray($restaurant->toRecommendationArray());

        return response(ShareCardImage::render($this->cardContent($restaurant, $halal['display'])), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
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
        ])->filter()->implode(' · ').' · Picked on MakanApa';
    }

    /**
     * schema.org Restaurant for an indexable (community-added) place: what the page itself shows,
     * and deliberately no rating or review fields.
     *
     * @return array<string, mixed>
     */
    private function structuredData(Restaurant $restaurant, string $url, ?string $priceRange): array
    {
        $cuisines = $restaurant->cuisines->pluck('name')->all();
        $category = RecommendationHeadline::categoryLabel($restaurant->food_category);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $restaurant->name,
            'url' => $url,
            'image' => $this->ogImage($restaurant->food_category),
            'address' => $restaurant->address
                ? ['@type' => 'PostalAddress', 'streetAddress' => $restaurant->address, 'addressCountry' => 'MY']
                : null,
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $restaurant->latitude, 'longitude' => (float) $restaurant->longitude],
            'servesCuisine' => $cuisines ?: ($category ? [$category] : null),
            'priceRange' => $priceRange,
        ], fn ($value) => $value !== null);
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

    /** @param  array{shortLabel: string, tone: string}  $halal */
    private function cardUrl(Restaurant $restaurant, array $halal): string
    {
        if (! ShareCardImage::canRender($restaurant->name)) {
            return asset(self::FALLBACK_CARD);
        }
        $version = substr(md5((string) json_encode($this->cardContent($restaurant, $halal))), 0, 12);

        return MarketingUrl::to("/og/p/{$restaurant->id}/{$version}.png");
    }

    /**
     * What the preview card shows. Halal only appears with HalalPresenter's own wording, and only
     * when there is something to say: "Help verify" on a thumbnail would read as a bug.
     *
     * @param  array{shortLabel: string, tone: string}  $halal
     * @return array{kicker: string, title: string, subtitle: ?string, detail: ?string, pills: list<array{label: string, background: array{int, int, int}, text: array{int, int, int}}>, footer: ?string}
     */
    private function cardContent(Restaurant $restaurant, array $halal): array
    {
        $pills = [];
        $halalColors = match ($halal['tone']) {
            'certified' => [[79, 122, 87], [253, 246, 236]],
            'friendly' => [[228, 236, 223], [59, 95, 66]],
            'non_halal' => [[232, 226, 219], [43, 28, 20]],
            default => null,
        };
        if ($halalColors !== null) {
            $pills[] = ['label' => $halal['shortLabel'], 'background' => $halalColors[0], 'text' => $halalColors[1]];
        }
        $price = self::priceLabel($restaurant->price_level);
        if ($price !== null) {
            // The card font has no "≈" glyph.
            $pills[] = ['label' => str_replace('≈', '~', $price), 'background' => [250, 225, 176], 'text' => [43, 28, 20]];
        }

        return [
            'kicker' => 'Someone sent you a food spot',
            'title' => $restaurant->name,
            'subtitle' => RecommendationHeadline::categoryLabel($restaurant->food_category),
            'detail' => $restaurant->address,
            'pills' => $pills,
            'footer' => parse_url(MarketingUrl::base(), PHP_URL_HOST) ?: null,
        ];
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
