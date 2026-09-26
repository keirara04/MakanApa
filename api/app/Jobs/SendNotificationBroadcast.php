<?php

namespace App\Jobs;

use App\Models\NotificationBroadcast;
use App\Services\NotificationBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The admin broadcast page's fan-out, off the request: writes the delivery rows and hands each
 * user's push to the queue (see NotificationBroadcastService::queue()). Not retried — a partial
 * run would re-insert deliveries and re-notify users who already got it.
 */
class SendNotificationBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Keep the queue connection's retry_after (REDIS_QUEUE_RETRY_AFTER) above this. */
    public int $timeout = 600;

    public function __construct(
        public readonly NotificationBroadcast $broadcast,
        public readonly Notification $notification,
    ) {}

    public function handle(NotificationBroadcastService $service): void
    {
        $service->fanOut($this->broadcast, $this->notification);
    }
}
