<?php

namespace App\Notifications;

use App\Models\RestaurantSubmission;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class CommunitySubmissionDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RestaurantSubmission $submission,
        private readonly string $decision, // 'approved' | 'rejected'
        private readonly ?string $reviewNote = null,
    ) {}

    public function via(object $notifiable): array
    {
        // 'database' rides along with the same preference gate as 'apn' — opting out of this
        // category means it doesn't show up in the in-app notifications list either, not just
        // that the push is suppressed.
        return $notifiable->wantsNotification(NotificationCategory::COMMUNITY_SUBMISSIONS) ? ['database', 'apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title(), $this->body(), [
            'type' => 'submission_decided',
            'submissionId' => $this->submission->id,
        ])->sound('default');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'submission_decided',
            'title' => $this->title(),
            'body' => $this->body(),
            'submissionId' => $this->submission->id,
            'decision' => $this->decision,
        ];
    }

    private function title(): string
    {
        return $this->decision === 'approved'
            ? 'Your submission was approved 🎉'
            : 'Your submission needs a look';
    }

    private function body(): string
    {
        return $this->decision === 'approved'
            ? "\"{$this->submission->name}\" is now live on MakanApa."
            : ($this->reviewNote ?: "\"{$this->submission->name}\" wasn't approved this time.");
    }
}
