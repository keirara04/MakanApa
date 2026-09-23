<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sanctum doesn't delete expired token rows on its own — prune daily so the
// personal_access_tokens table doesn't grow unbounded over the beta.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Retention policy for unmoderated Community Places photos — see the command's own doc comment.
Schedule::command('restaurant-photos:prune')->daily();

// Abandoned drafts -> cancelled (restaurant-photos:prune already deletes their photos).
Schedule::command('community:prune-drafts')->daily();

// Halal certificate expiry/expiring notices + review-state refresh. Read-time expiry already
// protects correctness if this is late; this drives admin queues and owner notifications.
Schedule::command('halal:lifecycle')->dailyAt('06:00');

// Judgment System: purge old state snapshots (rows/answers/outcomes stay for calibration).
Schedule::command('judgments:prune')->daily();

// Admin-scheduled push notifications (Filament: System > Scheduled Notifications) — checks for
// anything due every minute. Requires the scheduler itself to actually be running in production
// (`php artisan schedule:work`, or cron calling `schedule:run` every minute) — not automatic.
Schedule::command('notifications:dispatch-scheduled')->everyMinute();
