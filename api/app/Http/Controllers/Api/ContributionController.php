<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "What you've given back" — shown on the profile so contributing is visible, and part of why a
 * guest would want an account. Counts only what other people can actually see: approved places
 * and halal checks, published photos, visible posts. A guest simply gets zeros.
 */
class ContributionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $approved = RestaurantSubmission::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereIn('submission_type', ['new_place', 'halal_report'])
            ->selectRaw('submission_type, count(*) as total')
            ->groupBy('submission_type')
            ->pluck('total', 'submission_type');

        return response()->json([
            'trustedContributor' => (bool) $user->trusted_contributor,
            'placesAdded' => (int) ($approved['new_place'] ?? 0),
            'halalVerified' => (int) ($approved['halal_report'] ?? 0),
            'photosAdded' => RestaurantPhoto::query()
                ->where('uploaded_by', $user->id)
                ->where('is_active', true)
                ->where('disk', config('restaurant_photos.public_disk'))
                ->count(),
            'posts' => CommunityPost::query()->where('user_id', $user->id)->visible()->count(),
        ]);
    }
}
