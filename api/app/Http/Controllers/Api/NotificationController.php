<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Read-side of the in-app notification inbox. Writing happens exclusively through the 'database'
 * channel on the Notification classes in app/Notifications (see their via()) — there's no
 * separate write path here, this is just presenting what Laravel's own notifiable relationship
 * already stored.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(50)->get();

        return response()->json([
            'notifications' => $notifications->map(fn (DatabaseNotification $n) => $this->present($n)),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at->toIso8601String(),
            ...$notification->data,
        ];
    }
}
