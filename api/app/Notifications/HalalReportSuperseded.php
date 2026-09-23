<?php

namespace App\Notifications;

use App\Models\RestaurantHalalVerification;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

/** A newer verification replaced the one a contributor's report established, with a different status. */
class HalalReportSuperseded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RestaurantHalalVerification $replacement,
        private readonly string $restaurantName,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationCategory::COMMUNITY_SUBMISSIONS) ? ['database', 'apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title(), $this->body(), $this->payload())->sound('default');
    }

    public function toDatabase(object $notifiable): array
    {
        return [...$this->payload(), 'title' => $this->title(), 'body' => $this->body()];
    }

    private function payload(): array
    {
        return [
            'type' => 'halal_report_superseded',
            'restaurantId' => $this->replacement->restaurant_id,
            'status' => $this->replacement->status->value,
        ];
    }

    private function title(): string
    {
        return 'Halal status updated';
    }

    private function body(): string
    {
        return "Newer evidence changed the halal status of \"{$this->restaurantName}\" to {$this->replacement->status->label()}.";
    }
}
