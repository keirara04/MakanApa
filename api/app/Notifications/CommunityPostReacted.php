<?php

namespace App\Notifications;

use App\Models\CommunityPost;
use App\Models\User;
use App\Support\CommunityReaction;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnMessage;

class CommunityPostReacted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CommunityPost $post,
        private readonly CommunityReaction $reaction,
        private readonly User $reactor,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationCategory::COMMUNITY_REACTIONS) ? ['database', 'apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title(), $this->body(), [
            'type' => 'community_post_reacted',
            'postId' => $this->threadId(),
        ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'community_post_reacted',
            'title' => $this->title(),
            'body' => $this->body(),
            'postId' => $this->threadId(),
        ];
    }

    /** Always the top-level post, so tapping opens the thread whether a post or reply was reacted to. */
    private function threadId(): int
    {
        return $this->post->parent_id ?? $this->post->id;
    }

    private function title(): string
    {
        $emoji = match ($this->reaction) {
            CommunityReaction::Up => '👍',
            CommunityReaction::Fire => '🔥',
            CommunityReaction::Drool => '🤤',
        };

        return ($this->reactor->name ?: 'Someone')." reacted {$emoji} to your post";
    }

    private function body(): string
    {
        return Str::limit($this->post->body, 120);
    }
}
