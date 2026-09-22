<?php

namespace App\Console\Commands;

use App\Models\ScheduledNotification;
use App\Services\NotificationBroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs every minute (see routes/console.php). Claims each due row atomically (the
 * where('status', 'pending')->update(...) only succeeds for one runner) before actually
 * broadcasting, so an overlapping run — or this command and a manual admin cancel racing each
 * other — can never double-send the same schedule.
 */
#[Signature('notifications:dispatch-scheduled')]
#[Description('Send any scheduled notifications whose send time has arrived')]
class DispatchScheduledNotifications extends Command
{
    public function handle(NotificationBroadcastService $service): int
    {
        $due = ScheduledNotification::query()
            ->where('status', 'pending')
            ->where('send_at', '<=', now())
            ->get();

        foreach ($due as $scheduled) {
            $claimed = ScheduledNotification::where('id', $scheduled->id)
                ->where('status', 'pending')
                ->update(['status' => 'sent', 'sent_at' => now()]);

            if ($claimed === 0) {
                continue; // another run already claimed this one
            }

            $considered = $service->broadcast($scheduled->toNotification(), [
                'category' => $scheduled->category,
                'title' => $scheduled->title,
                'body' => $scheduled->body,
                'version' => $scheduled->version,
                'message' => $scheduled->message,
                'app_store_url' => $scheduled->app_store_url,
                'source' => 'scheduled',
                'scheduled_notification_id' => $scheduled->id,
                'created_by' => $scheduled->created_by,
            ]);

            $this->info("Sent scheduled notification #{$scheduled->id} ({$scheduled->category}) to {$considered} users.");
        }

        return self::SUCCESS;
    }
}
