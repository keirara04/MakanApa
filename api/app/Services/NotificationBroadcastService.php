<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Single dispatch path for "send this notification to every user" — shared by the release
 * artisan command and the Filament admin broadcast page, so that behavior only exists once.
 * The same Notification instance is reused across all users deliberately: notification classes
 * here are stateless value objects (title/body/payload fixed at construction), so reuse is safe
 * and avoids allocating one object per user.
 */
class NotificationBroadcastService
{
    /** @return int users considered (not all will actually receive a push — via() on the notification itself gates by preference/device tokens) */
    public function broadcast(Notification $notification): int
    {
        $considered = 0;

        // chunkById (not chunk) — rows can be updated/deleted while a broadcast this size runs.
        User::query()->chunkById(200, function ($users) use ($notification, &$considered) {
            foreach ($users as $user) {
                $user->notify($notification);
                $considered++;
            }
        });

        return $considered;
    }
}
