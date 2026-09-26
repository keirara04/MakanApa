<?php

namespace App\Console\Commands;

use App\Notifications\ModerationSlaBreached;
use App\Services\AdminAlertService;
use App\Services\ModerationSlaService;
use Filament\Facades\Filament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Hourly: if any moderation queue's oldest item has waited past the warning line (12h), tell
 * every active superadmin — bell + email — before the 24-hour promise is broken. Each queue
 * alerts at most once per 12 hours, so a stuck queue nags twice a day, not every hour.
 */
#[Signature('admin:moderation-sla')]
#[Description('Alert superadmins when a moderation queue has items older than 12 hours')]
class CheckModerationSla extends Command
{
    public function handle(ModerationSlaService $sla, AdminAlertService $alerts): int
    {
        $overdue = [];

        foreach ($sla->snapshot() as $key => $queue) {
            $hours = ModerationSlaService::ageInHours($queue['oldest']);
            if ($hours === null || $hours < ModerationSlaService::WARNING_HOURS) {
                continue;
            }

            // add() is atomic: only the first run in each 12h window claims the alert.
            if (! Cache::add("admin:moderation-sla-alerted:{$key}", true, now()->addHours(ModerationSlaService::WARNING_HOURS))) {
                continue;
            }

            $overdue[] = ['label' => $queue['label'], 'open' => $queue['open'], 'age' => ModerationSlaService::formatAge($queue['oldest'])];
        }

        if ($overdue === []) {
            $this->info('All moderation queues are within 12 hours.');

            return self::SUCCESS;
        }

        $admins = $alerts->superadmins();
        $dashboardUrl = Filament::getPanel('admin')->getUrl();
        $summary = collect($overdue)->map(fn (array $q) => "{$q['label']}: oldest {$q['age']}")->implode(' · ');

        $alerts->send('Moderation falling behind', $summary, $dashboardUrl, 'danger');
        Notification::send($admins, new ModerationSlaBreached($overdue, $dashboardUrl));

        $this->warn('Alerted '.$admins->count()." superadmin(s): {$summary}");

        return self::SUCCESS;
    }
}
