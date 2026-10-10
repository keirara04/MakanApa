<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;
use NotificationChannels\WebPush\WebPushChannel;

class ReleaseAnnouncement extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        private readonly string $version,
        private readonly string $message,
        private readonly ?string $appStoreUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        // 'database' rides along with the same preference gate as 'apn' — opting out of this
        // category means it doesn't show up in the in-app notifications list either, not just
        // that the push is suppressed.
        return $notifiable->wantsNotification(NotificationCategory::RELEASE_ANNOUNCEMENTS) ? ['database', 'apn', WebPushChannel::class] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create("MakanApa {$this->version} is here", $this->message, [
            'type' => 'release',
            'version' => $this->version,
            'appStoreUrl' => $this->appStoreUrl,
        ])->sound('default');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'release',
            'title' => "MakanApa {$this->version} is here",
            'body' => $this->message,
            'version' => $this->version,
            'appStoreUrl' => $this->appStoreUrl,
        ];
    }
}
