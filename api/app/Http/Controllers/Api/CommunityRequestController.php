<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "My university/area isn't listed" — logs a free-text request for an admin to act on
 * (manually creating the real University/Area row via Admin\UniversityController /
 * Admin\AreaController, then resolving this request). No auto-linking: the user picks the
 * real entry themselves the normal way once it exists.
 */
class CommunityRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:university,area'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        CommunityRequest::create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'name' => trim($data['name']),
            'status' => 'pending',
        ]);

        return response()->json(['requested' => true]);
    }
}
