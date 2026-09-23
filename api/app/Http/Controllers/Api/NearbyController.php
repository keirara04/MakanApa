<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\NearbyPickRequest;
use App\Http\Requests\NearbyRequest;
use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Services\Halal\HalalPresenter;
use App\Services\Places\PlaceNormalizer;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use App\Support\AreaPersonality;
use App\Support\DiscoveryMode;
use App\Support\Vibe;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nearby: map-first browse. Unlike Decide, candidates aren't preference-scored — the
 * viewport determines eligibility for the map itself; the Open now / Budget / Rating chips
 * only narrow the panel's areaSummary and the Pick-one-lah candidate pool (applyHardFilters()),
 * never the marker list index() returns — the map always shows everything in view. Scoring/
 * picking still goes through the same RecommendationService as Decide, only for "🍚 Pick one
 * lah" (pick()), never for the plain marker list.
 */
class NearbyController extends Controller
{
    use PresentsRecommendation;

    /** Shrinks a viewport by this fraction per side before treating it as the Pick-one-lah candidate pool — markers half hidden under floating search bar/filter chips/the Pick-one-lah button itself can't win. */
    private const PICK_VIEWPORT_INSET = 0.1;

    /** Matches the app's one existing budget chip (`NearbyView.swift`'s "≤ RM20" sends `budgetMax: 2`) — the area summary must not invent a second, disagreeing definition of "budget-friendly". */
    private const BUDGET_FRIENDLY_MAX_PRICE_LEVEL = 2;

    private const AREA_SUMMARY_TOP_N = 3;

    public function __construct(
        private readonly PlacesService $placesService,
        private readonly RecommendationService $recommendationService,
        private readonly PlaceNormalizer $normalizer,
        private readonly HalalPresenter $halalPresenter,
    ) {}

    public function index(NearbyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $mode = DiscoveryMode::fromRequest($data['mode'] ?? null);
        $vibe = Vibe::fromRequest($data['vibe'] ?? null);
        $data['halalOnly'] = $this->resolveHalalOnly($request, $data);

        try {
            $restaurants = $this->restaurantsInViewport($data, $mode, $vibe);
            // Unlike the Open now/Budget/Rating chips, Halal only DOES filter the map: it's a
            // standing dietary requirement, not a browsing chip, and the map is a discovery
            // surface too — a user who set it must never see non-halal pins.
            if ($data['halalOnly']) {
                $restaurants = $this->withoutNonHalal($restaurants);
            }
        } catch (RequestException $e) {
            Log::error('Places provider request failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not reach the places provider. Try again in a bit.'], 502);
        } catch (Throwable $e) {
            Log::error('Places lookup failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not find nearby places right now.'], 500);
        }

        return response()->json([
            // Every restaurant in the viewport, regardless of the Open now/Budget/Rating chips —
            // the map always shows everything; only the panel's counts/lists below respect them.
            'places' => array_map(fn (array $restaurant) => $this->presentMarker($restaurant), $restaurants),
            'areaSummary' => $this->buildAreaSummary($this->applyHardFilters($restaurants, $data), $data),
        ]);
    }

    public function pick(NearbyPickRequest $request): JsonResponse
    {
        $data = $request->validated();
        $viewport = $this->insetViewport($data['viewport']);
        $mode = DiscoveryMode::fromRequest($data['mode'] ?? null);
        $vibe = Vibe::fromRequest($data['vibe'] ?? null);
        $halalOnly = $this->resolveHalalOnly($request, $data);

        try {
            $authoritative = $this->applyHardFilters($this->restaurantsInViewport($viewport, $mode, $vibe), [
                'openNow' => $data['openNow'] ?? null,
                'budgetMax' => $data['budgetMax'] ?? null,
                'minRating' => $data['minRating'] ?? null,
                'halalOnly' => $halalOnly,
            ]);
        } catch (Throwable $e) {
            Log::error('Places lookup failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not find nearby places right now.'], 500);
        }

        // Never trust the client's visiblePlaceIds as the full candidate authority — only IDs
        // the server independently confirms sit inside the (inset) viewport are eligible.
        $visibleIds = array_flip($data['visiblePlaceIds']);
        $candidates = array_values(array_filter($authoritative, fn (array $r) => isset($visibleIds[$r['id']])));

        $preference = array_merge([
            'moodTags' => [],
            'cuisines' => [],
            'budgetMax' => $data['budgetMax'] ?? null,
            // Candidates are already scoped by viewport, not by the user's real-world distance —
            // Nearby lets you browse anywhere, not just around yourself. maxDistanceKm here only
            // needs to be large enough that eligibleRestaurants()'s hard distance cutoff never
            // false-excludes a viewport-valid candidate just because the user is browsing a part
            // of town far from where they're standing.
            'maxDistanceKm' => $this->maxCornerDistanceKm($viewport, $data['latitude'], $data['longitude']) + 0.01,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'halalOnly' => $halalOnly,
        ], $this->discoveryPreferenceExtras($data['mode'] ?? null, $data['vibe'] ?? null, $data['installationId'] ?? null));

        $ranked = $this->recommendationService->topCandidates($candidates, $preference, limit: count($candidates) ?: 1);
        $winner = $this->recommendationService->pick($ranked);

        // Mirrors RecommendationController::solo(): the Decision row is created either way, so
        // reroll/accept always have a consistent handle — a "nobody qualified" result isn't
        // an error, it's a normal (if disappointing) outcome of the filters the user picked.
        $clientToken = Str::random(40);

        $decision = Decision::create([
            'mode' => 'nearby',
            'client_token' => $clientToken,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'budget_max' => $data['budgetMax'] ?? null,
            'max_distance' => $preference['maxDistanceKm'],
            'selected_restaurant_id' => $winner['restaurant']['id'] ?? null,
            'discovery_mode' => $mode->value,
            'vibe' => $vibe?->value,
            'installation_id' => $data['installationId'] ?? null,
            'halal_only' => $halalOnly,
        ]);

        foreach ($ranked as $rank => $candidate) {
            DecisionRecommendation::create([
                'decision_id' => $decision->id,
                'restaurant_id' => $candidate['restaurant']['id'],
                'rank' => $rank + 1,
                'score' => $candidate['score'],
                'shown_at' => $winner && $candidate['restaurant']['id'] === $winner['restaurant']['id'] ? now() : null,
            ]);
        }

        if ($winner) {
            Restaurant::whereKey($winner['restaurant']['id'])->increment('impressions_count');
        }

        return response()->json([
            'decisionId' => $decision->id,
            'clientToken' => $clientToken,
            'algorithmVersion' => 'v1',
            'recommendation' => $winner
                ? $this->presentCandidate($winner, $this->enrichWinner($winner['restaurant']))
                : null,
        ]);
    }

    /**
     * Winner-only enrichment (photo/price/reviews), fetched only for the one restaurant the
     * user tapped — never for the whole marker list. Mirrors `enrichWinner()`'s "only ever
     * runs for the one chosen restaurant" rule, just triggered by a tap instead of a pick.
     */
    public function details(Restaurant $restaurant): JsonResponse
    {
        $restaurant->loadMissing('cuisines', 'tags');
        $data = $restaurant->toRecommendationArray();
        $enrichment = $this->enrichWinner($data);

        return response()->json([
            'id' => $data['id'],
            'name' => $data['name'],
            'foodCategory' => $data['food_category'],
            'rating' => $data['rating'],
            'priceLevel' => $data['price_level'],
            'cuisines' => $data['cuisines'],
            'openStatus' => $data['open_status'],
            'photos' => array_map(fn (array $photo) => $this->presentPhoto($photo), $enrichment['photos']),
            'reviews' => $enrichment['reviews'],
            'placeGoogleMapsUrl' => $enrichment['placeGoogleMapsUrl'],
            'closesAt' => $enrichment['closesAt'],
            'phone' => $data['phone'],
            'instagramHandle' => $data['instagram_handle'],
            'tiktokHandle' => $data['tiktok_handle'],
            'websiteUrl' => $data['website_url'],
            ...$this->presentDiscoveryExtras($data['id']),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>> normalized restaurant arrays within the given
     *                                          bounds — viewport-scoped only, never hard-filtered
     *                                          by Open now/Budget/Rating (see applyHardFilters()).
     */
    private function restaurantsInViewport(array $bounds, ?DiscoveryMode $mode = null, ?Vibe $vibe = null): array
    {
        [$centerLat, $centerLon, $radiusKm] = $this->viewportToCircle($bounds);

        $restaurants = $this->placesService->nearbyRestaurants($centerLat, $centerLon, $radiusKm, mode: $mode, vibe: $vibe);

        return array_values(array_filter($restaurants, fn (array $restaurant) => ! (
            $restaurant['latitude'] > $bounds['north'] || $restaurant['latitude'] < $bounds['south']
            || $restaurant['longitude'] > $bounds['east'] || $restaurant['longitude'] < $bounds['west']
        )));
    }

    /**
     * Open now / Budget / Rating chip filters, applied separately from the viewport scoping
     * above — used for the panel's areaSummary and the Pick-one-lah candidate pool, but never
     * for index()'s plain marker list: the map always shows every restaurant in view regardless
     * of which chips are active.
     */
    private function applyHardFilters(array $restaurants, array $filters): array
    {
        return array_values(array_filter($restaurants, function (array $restaurant) use ($filters) {
            if (($filters['openNow'] ?? null) && $restaurant['open_status'] !== 'open') {
                return false;
            }
            if (($filters['budgetMax'] ?? null) !== null && $restaurant['price_level'] !== null
                && $restaurant['price_level'] > $filters['budgetMax']) {
                return false;
            }
            if (($filters['halalOnly'] ?? false) && RecommendationService::isNonHalal($restaurant)) {
                return false;
            }
            if (($filters['minRating'] ?? null) !== null
                && ($restaurant['rating'] === null || $restaurant['rating'] < $filters['minRating'])) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Nearby's "what's around here" interpretation layer. Takes the already-hard-filtered
     * subset (applyHardFilters()) — deliberately narrower than the $restaurants the marker
     * list itself returns, since the map ignores the chips but this summary respects them.
     */
    private function buildAreaSummary(array $restaurants, array $bounds): array
    {
        $placeCount = count($restaurants);
        $openNowCount = count(array_filter($restaurants, fn (array $r) => $r['open_status'] === 'open'));
        $budgetFriendlyCount = count(array_filter($restaurants, fn (array $r) => $this->isBudgetFriendly($r['price_level'])));

        $topCategories = $this->topCategories($restaurants);

        $rated = array_values(array_filter($restaurants, fn (array $r) => $r['rating'] !== null));
        usort($rated, fn (array $a, array $b) => $b['rating'] <=> $a['rating']);
        $topRated = array_slice($rated, 0, self::AREA_SUMMARY_TOP_N);

        [$centerLat, $centerLon] = $this->viewportToCircle($bounds);
        $communityFinds = array_values(array_filter($restaurants, fn (array $r) => $r['provider'] === 'user_submitted'));
        usort($communityFinds, fn (array $a, array $b) => RecommendationService::distanceKm($centerLat, $centerLon, $a['latitude'], $a['longitude'])
            <=> RecommendationService::distanceKm($centerLat, $centerLon, $b['latitude'], $b['longitude']));
        $communityFinds = array_slice($communityFinds, 0, self::AREA_SUMMARY_TOP_N);

        return [
            'placeCount' => $placeCount,
            'openNowCount' => $openNowCount,
            'budgetFriendlyCount' => $budgetFriendlyCount,
            'topCategories' => $topCategories,
            'topRated' => array_map(fn (array $r) => $this->presentMarker($r), $topRated),
            'communityFinds' => array_map(fn (array $r) => $this->presentMarker($r), $communityFinds),
            'personalityTags' => AreaPersonality::forSummary($placeCount, $budgetFriendlyCount, $topCategories),
        ];
    }

    private function isBudgetFriendly(?int $priceLevel): bool
    {
        return $priceLevel !== null && $priceLevel <= self::BUDGET_FRIENDLY_MAX_PRICE_LEVEL;
    }

    /** @return array<int, array{label: string, count: int}> top categories, descending by count */
    private function topCategories(array $restaurants): array
    {
        $counts = [];
        foreach ($restaurants as $restaurant) {
            $category = $restaurant['food_category'] ?? null;
            if ($category === null) {
                continue;
            }
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        arsort($counts);

        $shaped = array_map(
            fn (string $label, int $count) => ['label' => $label, 'count' => $count],
            array_keys($counts), array_values($counts)
        );

        return array_slice($shaped, 0, self::AREA_SUMMARY_TOP_N);
    }

    private function withoutNonHalal(array $restaurants): array
    {
        return array_values(array_filter($restaurants, fn (array $r) => ! RecommendationService::isNonHalal($r)));
    }

    private function presentMarker(array $restaurant): array
    {
        return [
            'id' => $restaurant['id'],
            'name' => $restaurant['name'],
            'rating' => $restaurant['rating'],
            'priceLevel' => $restaurant['price_level'],
            'latitude' => $restaurant['latitude'],
            'longitude' => $restaurant['longitude'],
            'openStatus' => $restaurant['open_status'],
            'halal' => $this->halalPresenter->summaryFromArray($restaurant),
        ];
    }

    /** Shrinks a viewport toward its center by PICK_VIEWPORT_INSET on each side. */
    private function insetViewport(array $viewport): array
    {
        $latInset = ($viewport['north'] - $viewport['south']) * self::PICK_VIEWPORT_INSET;
        $lonInset = ($viewport['east'] - $viewport['west']) * self::PICK_VIEWPORT_INSET;

        return [
            'north' => $viewport['north'] - $latInset,
            'south' => $viewport['south'] + $latInset,
            'east' => $viewport['east'] - $lonInset,
            'west' => $viewport['west'] + $lonInset,
        ];
    }

    /** Center = viewport midpoint; radius = distance to the farthest corner, so the search circle fully contains the box. */
    private function viewportToCircle(array $bounds): array
    {
        $centerLat = ($bounds['north'] + $bounds['south']) / 2;
        $centerLon = ($bounds['east'] + $bounds['west']) / 2;

        return [$centerLat, $centerLon, $this->cornerRadiusKm($bounds, $centerLat, $centerLon)];
    }

    private function cornerRadiusKm(array $bounds, ?float $centerLat = null, ?float $centerLon = null): float
    {
        $centerLat ??= ($bounds['north'] + $bounds['south']) / 2;
        $centerLon ??= ($bounds['east'] + $bounds['west']) / 2;

        return RecommendationService::distanceKm($centerLat, $centerLon, $bounds['north'], $bounds['east']);
    }

    /** Largest distance from an arbitrary external point to any of the viewport's 4 corners. */
    private function maxCornerDistanceKm(array $bounds, float $lat, float $lon): float
    {
        $corners = [
            [$bounds['north'], $bounds['east']],
            [$bounds['north'], $bounds['west']],
            [$bounds['south'], $bounds['east']],
            [$bounds['south'], $bounds['west']],
        ];

        return max(array_map(
            fn (array $corner) => RecommendationService::distanceKm($lat, $lon, $corner[0], $corner[1]),
            $corners
        ));
    }
}
