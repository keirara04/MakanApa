<?php

namespace App\Console\Commands;

use App\Filament\Pages\ApiCosts;
use App\Notifications\ApiBudgetThresholdReached;
use App\Services\AdminAlertService;
use App\Services\Analytics\ApiCostReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

/**
 * Daily: once a provider's estimated month-to-date spend crosses a threshold of its monthly
 * budget (config/admin_budgets.php alert_thresholds, 80% and 100% by default), tell every
 * active superadmin — bell + email. Each threshold alerts at most once per provider per month;
 * crossing two at once sends only the higher one.
 */
#[Signature('admin:check-api-budget')]
#[Description('Alert superadmins when estimated Google Places / OpenRouter spend passes a budget threshold')]
class CheckApiBudget extends Command
{
    public function handle(ApiCostReport $report, AdminAlertService $alerts): int
    {
        $now = now();
        $thresholds = collect((array) Config::get('admin_budgets.alert_thresholds', [0.8, 1.0]))->map(fn ($t) => (float) $t)->sort()->values();
        $alerted = 0;

        foreach ($report->monthToDate($now) as $provider => $row) {
            if ($row['budget'] <= 0) {
                continue;
            }

            $ratio = $row['cost'] / $row['budget'];
            // add() is atomic: only the first run to cross a threshold this month claims it.
            $newlyCrossed = $thresholds
                ->filter(fn (float $t) => $ratio >= $t)
                ->filter(fn (float $t) => Cache::add("admin:api-budget-alerted:{$provider}:{$now->format('Y-m')}:{$t}", true, $now->copy()->endOfMonth()->addWeek()));

            if ($newlyCrossed->isEmpty()) {
                continue;
            }

            $this->notify($alerts, $row, $newlyCrossed->max());
            $alerted++;
        }

        $this->info($alerted === 0 ? 'All API spend is under its alert thresholds.' : "Sent {$alerted} budget alert(s).");

        return self::SUCCESS;
    }

    /** @param  array{label: string, budget: float, cost: float, projected: float}  $row */
    private function notify(AdminAlertService $alerts, array $row, float $threshold): void
    {
        $percent = (int) round($threshold * 100);
        $url = ApiCosts::getUrl(panel: 'admin');
        $summary = sprintf('$%s of $%s so far · projected $%s', number_format($row['cost'], 2), number_format($row['budget'], 2), number_format($row['projected'], 2));

        $alerts->send("{$row['label']} at {$percent}% of monthly budget", $summary, $url, $threshold >= 1 ? 'danger' : 'warning');
        Notification::send(
            $alerts->superadmins(),
            new ApiBudgetThresholdReached($row['label'], $threshold, $row['cost'], $row['budget'], $row['projected'], $url),
        );

        $this->warn("{$row['label']}: {$summary}");
    }
}
