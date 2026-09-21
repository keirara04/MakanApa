<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for universities — previously these only ever existed via seeders/tests/direct
 * DB writes; this is the first real endpoint that creates one, so a "request my university
 * isn't listed" (CommunityRequestController) has somewhere to be actioned.
 */
class UniversityController extends Controller
{
    public function index(): JsonResponse
    {
        $universities = University::query()
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'active'])
            ->map(fn (University $university) => [
                'id' => $university->id,
                'name' => $university->name,
                'shortName' => $university->short_name,
                'active' => $university->active,
            ]);

        return response()->json(['universities' => $universities]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'shortName' => ['required', 'string', 'max:50', 'unique:universities,short_name'],
        ]);

        $university = University::create([
            'name' => $data['name'],
            'short_name' => $data['shortName'],
            'active' => true,
        ]);

        return response()->json(['university' => ['shortName' => $university->short_name, 'name' => $university->name]]);
    }
}
