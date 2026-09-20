<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(private readonly PlacesService $placesService) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $results = $this->placesService->searchPlaces(
            trim($data['query']), (float) $data['latitude'], (float) $data['longitude']
        );

        return response()->json(['results' => array_map([$this, 'present'], $results)]);
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

    private function present(array $result): array
    {
        return [
            'provenance' => $result['provenance'],
            'restaurantId' => $result['id'] ?? null,
            'googlePlaceId' => $result['google_place_id'] ?? null,
            'name' => $result['name'],
            'foodCategory' => $result['food_category'] ?? null,
            'signatureDish' => $result['signature_dish'] ?? null,
            'cuisines' => $result['cuisines'] ?? [],
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'priceLevel' => $result['price_level'] ?? null,
            'rating' => $result['rating'] ?? null,
            'distanceKm' => $result['distance_km'] ?? null,
            'isCommunityFind' => $result['is_community_find'] ?? false,
        ];
    }
}
