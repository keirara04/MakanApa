<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Support\NotificationCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $this->resolved($request->user()),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->notification_preferences = array_merge(
            $this->resolved($user),
            $request->validated(),
        );
        $user->save();

        return response()->json([
            'preferences' => $this->resolved($user),
        ]);
    }

    /** @return array<string, bool> every category, defaults filled in for any key absent from the stored JSON */
    private function resolved($user): array
    {
        return array_combine(
            NotificationCategory::ALL,
            array_map(fn (string $category) => $user->wantsNotification($category), NotificationCategory::ALL),
        );
    }
}
