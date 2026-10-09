<?php

namespace App\Notifications;

use App\Models\AmbassadorApplication;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class AmbassadorApplicationDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AmbassadorApplication $application,
        private readonly string $decision, // approved | declined
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
            'type' => 'ambassador_application_decided',
            'applicationId' => $this->application->id,
            'decision' => $this->decision,
        ];
    }

    private function title(): string
    {
        return $this->decision === 'approved'
            ? "You're an ambassador now"
            : "Your ambassador application wasn't approved";
    }

    private function body(): string
    {
        $community = $this->application->communityName() ?? 'your community';

        return $this->decision === 'approved'
            ? "Welcome aboard. Open Community to start picking the best spots for {$community}."
            : ($this->reviewNote ?: "Thanks for applying to represent {$community}. You can apply again later.");
    }
}
