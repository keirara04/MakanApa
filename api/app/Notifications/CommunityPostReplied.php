<?php

namespace App\Notifications;

use App\Models\CommunityPost;
use App\Models\User;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnMessage;

class CommunityPostReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CommunityPost $parent,
        private readonly CommunityPost $reply,
        private readonly User $replier,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationCategory::COMMUNITY_REPLIES) ? ['database', 'apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title(), $this->body(), [
            'type' => 'community_post_replied',
            'postId' => $this->parent->id,
        ])->sound('default');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'community_post_replied',
            'title' => $this->title(),
            'body' => $this->body(),
            'postId' => $this->parent->id,
            'replyId' => $this->reply->id,
        ];
    }

    private function title(): string
    {
        return ($this->replier->name ?: 'Someone').' replied to your post';
    }

    private function body(): string
    {
        return Str::limit($this->reply->body, 120);
    }
}
