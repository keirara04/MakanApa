<?php

namespace App\Services;

use App\Models\NotificationBroadcast;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Context;

/**
 * Single dispatch path for "send this notification to every user" — shared by the release
 * artisan command, the scheduled-notification dispatcher, and the Filament admin broadcast page,
 * so that behavior only exists once. The same Notification instance is reused across all users
 * deliberately: notification classes here are stateless value objects (title/body/payload fixed
 * at construction), so reuse is safe and avoids allocating one object per user.
 *
 * Every call is logged to notification_broadcasts (the batch) and notification_deliveries (one
 * row per considered user), so the admin "Notification Log" page can show whether a push
 * actually reached each user. Deliveries start as skipped_preference (known synchronously,
 * mirrors the notification's own via() gate) or queued (handed to the queue) — queued rows flip
 * to sent/skipped_no_token/failed once the queue worker actually talks to APNs, via the
 * NotificationSent/NotificationFailed listeners in AppServiceProvider, matched back to this row
 * through Context (which Laravel automatically carries into the queued job for us).
 */
class NotificationBroadcastService
{
    /** @param array<string, mixed> $meta category/title/body/version/message/app_store_url/source/scheduled_notification_id/created_by for the log entry */
    /** @return int users considered (not all will actually receive a push — via() on the notification itself gates by preference/device tokens) */
    public function broadcast(Notification $notification, array $meta = []): int
    {
        $broadcast = NotificationBroadcast::create([
            'category' => $meta['category'] ?? null,
            'title' => $meta['title'] ?? null,
            'body' => $meta['body'] ?? null,
            'version' => $meta['version'] ?? null,
            'message' => $meta['message'] ?? null,
            'app_store_url' => $meta['app_store_url'] ?? null,
            'source' => $meta['source'] ?? 'manual',
            'scheduled_notification_id' => $meta['scheduled_notification_id'] ?? null,
            'created_by' => $meta['created_by'] ?? null,
        ]);

        $considered = 0;

        User::query()->chunkById(200, function ($users) use ($notification, $broadcast, &$considered) {
            foreach ($users as $user) {
                $channels = $notification->via($user);

                if (empty($channels)) {
                    $broadcast->deliveries()->create(['user_id' => $user->id, 'status' => 'skipped_preference']);

                    continue;
                }

                $delivery = $broadcast->deliveries()->create(['user_id' => $user->id, 'status' => 'queued']);

                Context::add('notification_delivery_id', $delivery->id);
                $user->notify($notification);
                Context::forget('notification_delivery_id');

                $considered++;
            }
        });

        $broadcast->update(['recipients_considered' => $considered]);

        return $considered;
    }
}
