<?php

namespace App\Notifications;

use App\Models\RestaurantSubmission;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

/** Approved / rejected / more evidence requested, for a halal_report submission. */
class HalalReportDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RestaurantSubmission $submission,
        private readonly string $decision, // approved | rejected | changes_requested
        private readonly ?string $reviewNote = null,
    ) {}

    public function via(object $notifiable): array
    {
        // Same preference gate as CommunitySubmissionDecided — halal evidence is a community contribution.
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
            'type' => 'halal_report_decided',
            'submissionId' => $this->submission->id,
            'restaurantId' => $this->submission->restaurant_id,
            'decision' => $this->decision,
        ];
    }

    private function title(): string
    {
        return match ($this->decision) {
            'approved' => 'Your halal report was approved',
            'changes_requested' => 'We need a bit more evidence',
            default => "Your halal report wasn't approved",
        };
    }

    private function body(): string
    {
        $name = $this->submission->name;

        return match ($this->decision) {
            'approved' => "Thanks! Your evidence for \"{$name}\" is now shown to other users.",
            'changes_requested' => $this->reviewNote ?: "Could you add a clearer photo for \"{$name}\"?",
            default => $this->reviewNote ?: "Your report for \"{$name}\" couldn't be verified this time.",
        };
    }
}
