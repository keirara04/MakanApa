<?php

namespace App\Services;

use App\Jobs\SendNotificationBroadcast;
use App\Models\NotificationBroadcast;
use App\Models\NotificationDelivery;
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
 *
 * Delivery rows are written one INSERT per chunk of users, not one per user. The admin page
 * uses queue() so the whole fan-out runs on the worker instead of inside the HTTP request.
 */
class NotificationBroadcastService
{
    private const CHUNK_SIZE = 200;

    /**
     * Synchronous: logs the batch and fans out right now (artisan command, scheduler).
     *
     * @param  array<string, mixed>  $meta  category/title/body/version/message/app_store_url/source/scheduled_notification_id/created_by for the log entry
     * @return int users considered (not all will actually receive a push — via() on the notification itself gates by preference/device tokens)
     */
    public function broadcast(Notification $notification, array $meta = []): int
    {
        return $this->fanOut($this->createBroadcast($meta), $notification);
    }

    /**
     * Logs the batch now and leaves the fan-out to a queued job — for the admin page, where a
     * loop over every user would otherwise hold the request (and the admin) until it finished.
     *
     * @param  array<string, mixed>  $meta  see broadcast()
     */
    public function queue(Notification $notification, array $meta = []): NotificationBroadcast
    {
        $broadcast = $this->createBroadcast($meta);

        SendNotificationBroadcast::dispatch($broadcast, $notification);

        return $broadcast;
    }

    /** @return int users considered — also stored on the broadcast as recipients_considered */
    public function fanOut(NotificationBroadcast $broadcast, Notification $notification): int
    {
        $considered = 0;

        User::query()->chunkById(self::CHUNK_SIZE, function ($users) use ($notification, $broadcast, &$considered) {
            [$reachable, $skipped] = $users->partition(fn (User $user) => ! empty($notification->via($user)));
            $reachableIds = array_values($reachable->modelKeys());

            $this->insertDeliveries($broadcast, array_values($skipped->modelKeys()), 'skipped_preference');
            $this->insertDeliveries($broadcast, $reachableIds, 'queued');

            // The ids Context must carry into each queued push, read back in one query.
            $deliveryIds = $reachableIds === [] ? collect() : NotificationDelivery::query()
                ->where('notification_broadcast_id', $broadcast->id)
                ->whereIn('user_id', $reachableIds)
                ->pluck('id', 'user_id');

            foreach ($reachable as $user) {
                Context::add('notification_delivery_id', $deliveryIds[$user->id]);
                $user->notify($notification);
                Context::forget('notification_delivery_id');

                $considered++;
            }
        });

        $broadcast->update(['recipients_considered' => $considered]);

        return $considered;
    }

    /** @param array<string, mixed> $meta */
    private function createBroadcast(array $meta): NotificationBroadcast
    {
        return NotificationBroadcast::create([
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
    }

    /** @param list<int> $userIds */
    private function insertDeliveries(NotificationBroadcast $broadcast, array $userIds, string $status): void
    {
        if ($userIds === []) {
            return;
        }

        $now = now();

        NotificationDelivery::insert(array_map(fn (int $userId) => [
            'notification_broadcast_id' => $broadcast->id,
            'user_id' => $userId,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ], $userIds));
    }
}
