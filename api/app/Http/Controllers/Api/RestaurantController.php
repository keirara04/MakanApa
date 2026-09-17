<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantSave;
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
        ]);

        return response()->json(['saved' => true]);
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
