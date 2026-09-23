<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchMiss;
use App\Services\Halal\HalalPresenter;
use App\Services\PlacesService;
use App\Support\RecommendationHeadline;
use App\Support\ShareLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * General restaurant search for Nearby's search box — distinct from
 * RestaurantSubmissionController::search(), which only serves the "add a place" dedupe flow.
 * This is browse/discovery: name, food/dish, category, and cuisine, ranked by match quality
 * with MakanApa's own DB searched first and Google only as a thin-results fallback (see
 * PlacesService::searchPlaces() for why).
 */
class PlaceSearchController extends Controller
{
    public function __construct(
        private readonly PlacesService $placesService,
        private readonly HalalPresenter $halalPresenter,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radiusKm' => ['nullable', 'numeric', 'between:1,25'],
            'halal' => ['nullable', 'boolean'],
            'includeGoogle' => ['nullable', 'boolean'],
        ]);

        $query = trim($data['query']);
        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];
        // Same rule as Decide/Nearby: an explicit `halal` param wins, else the account preference.
        $halalOnly = $request->filled('halal') ? $request->boolean('halal') : (bool) $request->user()?->halal_preference;

        $results = $this->placesService->searchPlaces(
            $query, $latitude, $longitude,
            radiusKm: (float) ($data['radiusKm'] ?? 6.0),
            halalOnly: $halalOnly,
            includeGoogle: $request->filled('includeGoogle') ? $request->boolean('includeGoogle') : null,
        );
        $meta = $this->placesService->lastSearchMeta();

        if ($results === [] && mb_strlen($query) >= 3) {
            SearchMiss::record(mb_strtolower($query), $latitude, $longitude);
        }

        return response()->json([
            'results' => array_map([$this, 'present'], $results),
            'meta' => [
                'radiusKm' => $meta['radius_km'],
                'source' => $meta['source'],
                'googleAvailable' => $meta['google_available'],
                'widerRadiusKm' => $meta['wider_radius_km'],
                'total' => $meta['total'],
            ],
            'suggestions' => $meta['suggestions'],
        ]);
    }

    /**
     * Turns a google_fallback result into a canonical `restaurants` row — called once the user
     * taps that result, before its detail sheet opens, so the client only ever needs one detail
     * path (a real restaurant_id), never a separate Google-only view.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'googlePlaceId' => ['required', 'string', 'max:255'],
        ]);

        try {
            $restaurant = $this->placesService->resolveGooglePlace($data['googlePlaceId']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Could not resolve this place right now.'], 502);
        }

        return response()->json(['restaurant' => $this->present($restaurant)]);
    }

    /**
     * One summary shape for a search row, a resolved place and the sheet's first paint — enough
     * to render the detail sheet with no "unknown → correct" flicker while details load.
     * `id`/`category`/`cuisine`/`openStatus` are canonical; `restaurantId`/`foodCategory`/
     * `cuisines`/`signatureDish` are kept only so already-shipped builds keep decoding.
     *
     * @deprecated-keys restaurantId, foodCategory, cuisines, signatureDish — remove once every
     *                  TestFlight build reads the canonical keys.
     */
    private function present(array $result): array
    {
        $cuisines = $result['cuisines'] ?? [];

        return [
            'provenance' => $result['provenance'],
            'id' => $result['id'] ?? null,
            'googlePlaceId' => $result['google_place_id'] ?? null,
            'name' => $result['name'],
            'address' => $result['address'] ?? null,
            'category' => RecommendationHeadline::categoryLabel($result['food_category'] ?? null),
            'cuisine' => isset($cuisines[0]) ? Str::headline($cuisines[0]) : null,
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'priceLevel' => $result['price_level'] ?? null,
            'rating' => $result['rating'] ?? null,
            'distanceKm' => $result['distance_km'] ?? null,
            'openStatus' => $result['open_status'] ?? 'unknown',
            'closesAt' => $result['closes_at'] ?? null,
            'halal' => $this->halalPresenter->summaryFromArray($result),
            'isCommunityFind' => $result['is_community_find'] ?? false,
            'shareUrl' => isset($result['id']) ? ShareLinks::place($result['id'], $result['name']) : null,
            'groupKey' => $result['group_key'] ?? null,
            'groupSize' => $result['group_size'] ?? 1,

            'restaurantId' => $result['id'] ?? null,
            'foodCategory' => $result['food_category'] ?? null,
            'signatureDish' => $result['signature_dish'] ?? null,
            'cuisines' => $cuisines,
        ];
    }
}
