<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Http\JsonResponse;

class UniversityController extends Controller
{
    public function index(): JsonResponse
    {
        $universities = University::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['short_name', 'name'])
            ->map(fn (University $university) => [
                'shortName' => $university->short_name,
                'name' => $university->name,
            ]);

        return response()->json(['universities' => $universities]);
    }
}
