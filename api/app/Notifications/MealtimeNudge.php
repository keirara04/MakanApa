<?php

namespace App\Notifications;

use App\Models\MealNudge;
use App\Support\NotificationCategory;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

/**
 * "Lunch dah?" — sent inline by MealNudgeDispatcher (not queued: the dispatcher already runs in
 * the scheduler, and sending inline lets it record a failure on the nudge row). Push only; no
 * inbox entry — a mealtime nudge is stale an hour later.
 */
class MealtimeNudge extends Notification
{
    public function __construct(
        private readonly MealNudge $nudge,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->wantsNotification(NotificationCategory::MEALTIME_NUDGES) ? ['apn'] : [];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create($this->title, $this->body, array_filter([
            'type' => 'meal_nudge',
            'nudgeId' => $this->nudge->id,
            'restaurantId' => $this->nudge->restaurant_id,
        ], fn ($value) => $value !== null));
    }
}
