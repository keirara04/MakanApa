<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email half of admin:check-api-budget — a provider's estimated month-to-date spend crossed a
 * budget threshold. Mail only; the Filament bell gets its own database notification.
 */
class ApiBudgetThresholdReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $providerLabel,
        private readonly float $threshold,
        private readonly float $cost,
        private readonly float $budget,
        private readonly float $projected,
        private readonly string $pageUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $percent = (int) round($this->threshold * 100);

        return (new MailMessage)
            ->subject("MakanApa: {$this->providerLabel} at {$percent}% of this month's budget")
            ->line(sprintf(
                'Estimated %s spend this month is $%s of a $%s budget.',
                $this->providerLabel,
                number_format($this->cost, 2),
                number_format($this->budget, 2),
            ))
            ->line(sprintf('At the current rate it will reach about $%s by month end.', number_format($this->projected, 2)))
            ->action('Open API costs', $this->pageUrl)
            ->line('This is an estimate from MakanApa\'s own call counters — check the provider billing console for the invoice.');
    }
}
