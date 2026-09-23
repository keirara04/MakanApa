<?php

namespace App\Notifications;

use App\Models\RestaurantSubmission;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class OwnerClaimDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RestaurantSubmission $submission,
        private readonly string $decision, // approved | rejected | changes_requested
        private readonly ?string $reviewNote = null,
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
            'type' => 'owner_claim_decided',
            'submissionId' => $this->submission->id,
            'restaurantId' => $this->submission->restaurant_id,
            'decision' => $this->decision,
        ];
    }

    private function title(): string
    {
        return match ($this->decision) {
            'approved' => "You're verified as the owner",
            'changes_requested' => 'We need more proof of ownership',
            default => "Ownership claim wasn't approved",
        };
    }

    private function body(): string
    {
        return match ($this->decision) {
            'approved' => "You can now submit halal certificates and updates for \"{$this->submission->name}\".",
            default => $this->reviewNote ?: "Your claim for \"{$this->submission->name}\" couldn't be verified.",
        };
    }
}
