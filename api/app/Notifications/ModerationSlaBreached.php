<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email half of admin:moderation-sla — a queue has had something waiting past the warning line.
 * Mail only: the Filament bell gets its own database notification, and the app inbox never
 * carries moderation duties.
 */
class ModerationSlaBreached extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{label: string, open: int, age: string}>  $queues
     */
    public function __construct(private readonly array $queues, private readonly string $dashboardUrl) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('MakanApa moderation is falling behind')
            ->line('These queues have items waiting longer than we promise users:');

        foreach ($this->queues as $queue) {
            $message->line("• {$queue['label']}: {$queue['open']} open, oldest waiting {$queue['age']}");
        }

        return $message
            ->action('Open the admin dashboard', $this->dashboardUrl)
            ->line('Reports should be reviewed within 24 hours of being made.');
    }
}
