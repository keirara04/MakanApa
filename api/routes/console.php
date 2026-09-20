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
