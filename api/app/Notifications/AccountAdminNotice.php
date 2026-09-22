<?php

namespace App\Notifications;

use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class AccountAdminNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
    ) {}

    public function via(object $notifiable): array
    {
        // 'database' rides along with the same preference gate as 'apn' — opting out of this
        // category means it doesn't show up in the in-app notifications list either, not just
        // that the push is suppressed.
        return $notifiable->wantsNotification(NotificationCategory::ACCOUNT_ADMIN) ? ['database', 'apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title, $this->body, [
            'type' => 'account_admin',
        ])->sound('default');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'account_admin',
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
