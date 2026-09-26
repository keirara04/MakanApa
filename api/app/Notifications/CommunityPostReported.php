<?php

namespace App\Notifications;

use App\Filament\Resources\CommunityPosts\CommunityPostResource;
use App\Models\CommunityPost;
use App\Support\CommunityReportReason;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnMessage;

/**
 * Tells superadmins a community post was reported, so the Terms' promise to act on reports
 * within 24 hours (Apple guideline 1.2) doesn't depend on someone happening to open Filament.
 * Deliberately ignores notification preferences: it's a moderation duty, not a user-facing
 * category a superadmin can opt out of. No 'database' channel — the app's inbox is for
 * user-facing notification types only; the Filament badge already lists open reports.
 */
class CommunityPostReported extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CommunityPost $post,
        private readonly CommunityReportReason $reason,
        private readonly bool $autoHidden,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['apn', 'mail'] : ['apn'];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title(), $this->excerpt(), [
            'type' => 'community_post_reported',
            'postId' => $this->post->id,
        ])->sound('default');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line('Reason: '.$this->reason->label())
            ->line('Post: "'.$this->excerpt().'"')
            ->line($this->autoHidden
                ? 'It reached the report threshold and is now hidden until reviewed.'
                : 'It is still visible.')
            ->action('Review the post', CommunityPostResource::getUrl('view', ['record' => $this->post], panel: 'admin'))
            ->line('Review within 24 hours: remove it and suspend the author if it breaks the Community Guidelines, or dismiss the reports.');
    }

    private function title(): string
    {
        return $this->autoHidden ? 'Reported post auto-hidden' : 'Community post reported';
    }

    private function excerpt(): string
    {
        return Str::limit($this->post->body, 120);
    }
}
