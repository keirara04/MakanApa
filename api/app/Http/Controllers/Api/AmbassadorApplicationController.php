<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmbassadorApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Become an ambassador" for the member's own community. The app reads the latest application
 * first and shows its status instead of the form, so the 422s below are only race fallbacks.
 */
class AmbassadorApplicationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $application = AmbassadorApplication::query()
            ->where('user_id', $request->user()->id)
            ->with(['university', 'area'])
            ->latest('id')
            ->first();

        return response()->json(['application' => $application ? $this->present($application) : null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:500'],
            'instagramHandle' => ['nullable', 'string', 'max:60'],
        ]);
        $user = $request->user();

        if ($user->ambassadorOf() !== null) {
            throw ValidationException::withMessages(['reason' => "You're already an ambassador."]);
        }
        $universityId = $user->universityId();
        $areaId = $universityId === null ? $user->areaId() : null;
        if ($universityId === null && $areaId === null) {
            throw ValidationException::withMessages(['reason' => 'Join a university or area community first.']);
        }
        if (AmbassadorApplication::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['reason' => 'Your application is already being reviewed.']);
        }

        $application = AmbassadorApplication::create([
            'user_id' => $user->id,
            'university_id' => $universityId,
            'area_id' => $areaId,
            'reason' => trim($data['reason']),
            'instagram_handle' => isset($data['instagramHandle']) ? ltrim(trim($data['instagramHandle']), '@') ?: null : null,
            'status' => 'pending',
        ]);

        return response()->json(['application' => $this->present($application->load(['university', 'area']))], 201);
    }

    /** @return array<string, mixed> */
    private function present(AmbassadorApplication $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status,
            'communityType' => $application->communityType(),
            'communityName' => $application->communityName(),
            'reviewNote' => $application->review_note,
            'createdAt' => $application->created_at?->toIso8601String(),
        ];
    }
}
