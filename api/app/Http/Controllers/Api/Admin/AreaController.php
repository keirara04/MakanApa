<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index(): JsonResponse
    {
        $areas = Area::query()
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'active'])
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'shortName' => $area->short_name,
                'active' => $area->active,
            ]);

        return response()->json(['areas' => $areas]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'shortName' => ['required', 'string', 'max:50', 'unique:areas,short_name'],
        ]);

        $area = Area::create([
            'name' => $data['name'],
            'short_name' => $data['shortName'],
            'active' => true,
        ]);

        return response()->json(['area' => ['shortName' => $area->short_name, 'name' => $area->name]]);
    }
}
