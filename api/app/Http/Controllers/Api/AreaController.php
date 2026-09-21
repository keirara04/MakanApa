<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\JsonResponse;

class AreaController extends Controller
{
    public function index(): JsonResponse
    {
        $areas = Area::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['short_name', 'name'])
            ->map(fn (Area $area) => [
                'shortName' => $area->short_name,
                'name' => $area->name,
            ]);

        return response()->json(['areas' => $areas]);
    }
}
