<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Real open/close session tracking, distinct from Sanctum's `personal_access_tokens.last_used_at`
 * (which only proves "made an authenticated API call sometime," not "opened/closed the app" or
 * how long they stayed). The iOS client calls `start` on scenePhase -> .active and `end` on
 * scenePhase -> .background, fire-and-forget — a missed `end` (kill, crash, no network) just
 * leaves `ended_at` null, which is still a usable "opened at X" data point.
 */
class AppSessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['installationId' => ['nullable', 'string', 'max:255']]);

        $session = AppSession::create([
            'user_id' => $request->user()->id,
            'installation_id' => $data['installationId'] ?? null,
            'started_at' => now(),
        ]);

        return response()->json(['sessionId' => $session->id]);
    }

    /** Idempotent — a retried `end` call (network retry) on an already-ended session is a no-op, not an error. */
    public function end(Request $request, AppSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        if ($session->ended_at === null) {
            $session->update(['ended_at' => now()]);
        }

        return response()->json(['ended' => true]);
    }
}
