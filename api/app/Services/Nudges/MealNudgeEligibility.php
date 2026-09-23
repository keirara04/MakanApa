<?php

namespace App\Services\Nudges;

use App\Models\AppSession;
use App\Models\Decision;
use App\Models\MealNudge;
use App\Models\MealNudgeState;
use App\Models\User;
use App\Support\NotificationCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Should this user get anything right now? Returns null when yes, else the reason not — every
 * rule that keeps nudges from being noise lives here and nowhere else.
 */
class MealNudgeEligibility
{
    public function __construct(private readonly MealNudgeTiming $timing) {}

    public function reasonToSkip(User $user, MealNudgeState $state, CarbonImmutable $now): ?string
    {
        if (! Config::get('nudges.enabled') || ! $user->wantsNotification(NotificationCategory::MEALTIME_NUDGES)) {
            return 'opted_out';
        }
        if ($state->stopped_at !== null) {
            return 'stopped';
        }
        if ($state->isPaused()) {
            return 'paused';
        }
        if ($user->routeNotificationForApn() === []) {
            return 'no_device';
        }

        $local = $now->setTimezone((string) Config::get('brain.timezone', 'Asia/Kuala_Lumpur'));
        if ($this->timing->inQuietHours($local)) {
            return 'quiet_hours';
        }
        if (MealNudge::where('user_id', $user->id)->whereDate('local_date', $local->toDateString())->exists()) {
            return 'already_today';
        }
        if (Decision::where('user_id', $user->id)->where('created_at', '>=', $now->subHours((int) Config::get('nudges.recent_decision_hours', 3)))->exists()) {
            return 'recently_decided';
        }
        if (AppSession::where('user_id', $user->id)->where('started_at', '>=', $now->subMinutes((int) Config::get('nudges.recent_app_open_minutes', 60)))->exists()) {
            return 'recently_active';
        }

        return null;
    }
}
