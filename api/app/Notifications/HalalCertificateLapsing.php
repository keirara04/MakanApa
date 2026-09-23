<?php

namespace App\Notifications;

use App\Models\RestaurantHalalCertificate;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

/**
 * Certificate lifecycle notice for the restaurant's verified owner and the contributor whose
 * report established it: expiring (30/7 days out), expired, or revoked.
 */
class HalalCertificateLapsing extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RestaurantHalalCertificate $certificate,
        private readonly string $restaurantName,
        private readonly string $stage, // expiring_30 | expiring_7 | expired | revoked
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
            'type' => 'halal_certificate_'.$this->stage,
            'restaurantId' => $this->certificate->restaurant_id,
        ];
    }

    private function title(): string
    {
        return match ($this->stage) {
            'expired' => 'Halal certificate expired',
            'revoked' => 'Halal certificate withdrawn',
            default => 'Halal certificate expiring soon',
        };
    }

    private function body(): string
    {
        $date = $this->certificate->expires_at->format('j M Y');

        return match ($this->stage) {
            'expired' => "The halal certificate for \"{$this->restaurantName}\" expired on {$date}. Got the renewed one? Help us re-verify.",
            'revoked' => "The halal certificate for \"{$this->restaurantName}\" is no longer valid.",
            default => "The halal certificate for \"{$this->restaurantName}\" expires on {$date}. Share the renewal when it's out.",
        };
    }
}
