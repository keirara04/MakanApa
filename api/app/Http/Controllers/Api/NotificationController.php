<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Read-side of the in-app notification inbox. Writing happens exclusively through the 'database'
 * channel on the Notification classes in app/Notifications (see their via()) — there's no
 * separate write path here, this is just presenting what Laravel's own notifiable relationship
 * already stored. Filament's admin-panel bell notifications share the same table (superadmins are
 * app users too), so every query here leaves them out — the app only ever sees its own types.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->appNotifications($request->user())->latest()->limit(50)->get();

        return response()->json([
            'notifications' => $notifications->map(fn (DatabaseNotification $n) => $this->present($n)),
            'unreadCount' => $this->appNotifications($request->user())->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $record = $this->appNotifications($request->user())->where('id', $notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->appNotifications($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function appNotifications(User $user): MorphMany
    {
        return $user->notifications()->where('type', '!=', FilamentDatabaseNotification::class);
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
