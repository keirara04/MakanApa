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
        return $notifiable->wantsNotification(NotificationCategory::COMMUNITY_SUBMISSIONS) ? ['apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        $title = $this->decision === 'approved'
            ? 'Your submission was approved 🎉'
            : 'Your submission needs a look';

        $body = $this->decision === 'approved'
            ? "\"{$this->submission->name}\" is now live on MakanApa."
            : ($this->reviewNote ?: "\"{$this->submission->name}\" wasn't approved this time.");

        return ApnMessage::create($title, $body, [
            'type' => 'submission_decided',
            'submissionId' => $this->submission->id,
        ])->sound('default');
    }
}
