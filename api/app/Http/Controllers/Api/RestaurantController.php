<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantSave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Save/unsave is idempotent by construction — a unique (restaurant_id, installation_id) row,
 * not a counter — so double-taps or client retries can't inflate a "saved_count." No login
 * exists app-wide; installationId is a client-generated anonymous device id.
 */
class RestaurantController extends Controller
{
    public function save(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate(['installationId' => ['required', 'string', 'max:100']]);

        RestaurantSave::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'installation_id' => $data['installationId'],
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
