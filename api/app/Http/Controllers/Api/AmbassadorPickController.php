<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmbassadorPick;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\CommunityContentFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An ambassador's own picks for the community they represent (shown to its members as the
 * "Ambassador picks" rail — see CommunityController::ambassadorPicks()).
 */
class AmbassadorPickController extends Controller
{
    /** My current picks (place + note), so the app can show "In your picks" and prefill edits. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'picks' => $user->ambassadorOf()
                ? $this->currentPicks($user)->get(['restaurant_id', 'note'])
                    ->map(fn (AmbassadorPick $pick) => ['restaurantId' => $pick->restaurant_id, 'note' => $pick->note])
                    ->all()
                : [],
            'max' => AmbassadorPick::MAX_PER_AMBASSADOR,
        ]);
    }

    /** Adds a pick, or updates its note when the place is already picked. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->ambassadorOf()) {
            return response()->json(['message' => 'Only ambassadors can add picks.'], 403);
        }

        $data = $request->validate([
            'restaurantId' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:140'],
        ]);

        $restaurant = Restaurant::where('is_active', true)->find($data['restaurantId']);
        if (! $restaurant) {
            return response()->json(['message' => 'That place is no longer available.'], 422);
        }

        $note = isset($data['note']) ? trim($data['note']) : null;
        $note = $note === '' ? null : $note;
        if ($note !== null && $reason = CommunityContentFilter::rejectionReason($note)) {
            return response()->json(['message' => $reason], 422);
        }

        $existing = $this->currentPicks($user)->where('restaurant_id', $restaurant->id)->first();

        if (! $existing && $this->currentPicks($user)->count() >= AmbassadorPick::MAX_PER_AMBASSADOR) {
            return response()->json([
                'message' => 'You\'ve used all '.AmbassadorPick::MAX_PER_AMBASSADOR.' picks. Remove one to add another.',
            ], 422);
        }

        // A pick made for a previous community is replaced, not duplicated (one row per place).
        $pick = AmbassadorPick::updateOrCreate(
            ['user_id' => $user->id, 'restaurant_id' => $restaurant->id],
            [
                'university_id' => $user->ambassador_university_id,
                'area_id' => $user->ambassador_area_id,
                'note' => $note,
            ],
        );

        return response()->json([
            'pick' => ['restaurantId' => $pick->restaurant_id, 'note' => $pick->note],
        ], $pick->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $restaurant): JsonResponse
    {
        AmbassadorPick::where('user_id', $request->user()->id)->where('restaurant_id', $restaurant)->delete();

        return response()->json(['removed' => true]);
    }

    /** @return Builder<AmbassadorPick> picks counted against the community this user represents now */
    private function currentPicks(User $user): Builder
    {
        return AmbassadorPick::query()
            ->where('user_id', $user->id)
            ->where('university_id', $user->ambassador_university_id)
            ->where('area_id', $user->ambassador_area_id);
    }
}
