<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealNudge;
use App\Models\MealNudgeState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The nudge funnel as reported by the app: opened → place_opened → makan_sini /
 * quick_pick_started. Every event is idempotent (first timestamp wins), and opening one resets
 * the unopened streak the back-off counts.
 */
class MealNudgeController extends Controller
{
    public function event(Request $request, MealNudge $nudge): JsonResponse
    {
        abort_unless($nudge->user_id === $request->user()->id, 404);

        $event = $request->validate(['event' => ['required', Rule::in(MealNudge::EVENTS)]])['event'];
        $now = now();

        $changes = ['opened_at' => $nudge->opened_at ?? $now];
        if ($event === 'place_opened') {
            $changes['place_opened_at'] = $nudge->place_opened_at ?? $now;
        }
        if (in_array($event, ['makan_sini', 'quick_pick_started'], true) && $nudge->acted_at === null) {
            $changes['acted_at'] = $now;
            $changes['action'] = $event;
        }
        $nudge->update($changes);

        MealNudgeState::where('user_id', $nudge->user_id)->update(['unopened_streak' => 0]);

        return response()->json(['recorded' => true]);
    }
}
