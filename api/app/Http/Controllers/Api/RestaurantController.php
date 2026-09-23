<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantSave;
use App\Services\SearchChoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Save/unsave is idempotent by construction — a unique (restaurant_id, installation_id) row,
 * not a counter — so double-taps or client retries can't inflate a "saved_count." installationId
 * is a client-generated anonymous device id, kept alongside the now-authenticated user_id so a
 * beta tester's saves aren't stranded on the device id if they log in from elsewhere later.
 */
class RestaurantController extends Controller
{
    public function save(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate(['installationId' => ['required', 'string', 'max:100']]);

        RestaurantSave::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'installation_id' => $data['installationId'],
        ], [
            'user_id' => $request->user()?->id,
            'university_id' => $request->user()?->universityId(),
            'area_id' => $request->user()?->areaId(),
        ]);

        return response()->json(['saved' => true]);
    }

    /**
     * "Makan sini" from search — see SearchChoiceService. Returns a decision handle (id + token)
     * so the client can follow up exactly like any accepted pick (vibe tag later, Recent).
     */
    public function choose(Request $request, Restaurant $restaurant, SearchChoiceService $choices): JsonResponse
    {
        abort_unless($restaurant->is_active, 404);

        $data = $request->validate([
            'clientChoiceId' => ['required', 'uuid'],
            'installationId' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'search' => ['nullable', 'array'],
            'search.query' => ['nullable', 'string', 'max:100'],
            'search.radiusKm' => ['nullable', 'numeric', 'between:1,25'],
            'search.source' => ['nullable', 'in:local,google,mixed'],
        ]);

        $result = $choices->choose($request->user(), $restaurant, $data['clientChoiceId'], [
            'installationId' => $data['installationId'] ?? null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'query' => $data['search']['query'] ?? null,
            'radiusKm' => isset($data['search']['radiusKm']) ? (float) $data['search']['radiusKm'] : null,
            'source' => $data['search']['source'] ?? null,
        ]);

        return response()->json([
            'decisionId' => $result['decision']->id,
            'clientToken' => $result['decision']->client_token,
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function unsave(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate(['installationId' => ['required', 'string', 'max:100']]);

        RestaurantSave::where('restaurant_id', $restaurant->id)
            ->where('installation_id', $data['installationId'])
            ->delete();

        return response()->json(['saved' => false]);
    }
}
