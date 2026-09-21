<?php

namespace App\Filament\Widgets;

use App\Models\AppSession;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Real app-open events (AppSessionController::start(), fired on the iOS client's scenePhase ->
 * .active) — distinct from the "New users" widget (signups) and from Sanctum's
 * last_used_at-based "weekly active users" stat (which only proves an authenticated API call
 * happened, not that the app was actually opened/closed by a person).
 */
class AppSessionsChartWidget extends LineChartWidget
{
    protected static ?int $sort = 6;

    public ?string $filter = 'day';

    public function getHeading(): string
    {
        return 'App opens';
    }

    protected function getFilters(): ?array
    {
        return [
            'hour' => 'Last 24 hours',
            'day' => 'Last 30 days',
            'week' => 'Last 12 weeks',
            'month' => 'Last 12 months',
        ];
    }

    protected function getData(): array
    {
        [$bucket, $since, $format] = match ($this->filter) {
            'hour' => ['hour', now()->subHours(24), 'ga'],
            'week' => ['week', now()->subWeeks(12), 'M j'],
            'month' => ['month', now()->subMonths(12), 'M Y'],
            default => ['day', now()->subDays(30), 'M j'],
        };

        $rows = AppSession::query()
            ->where('started_at', '>=', $since)
            ->selectRaw("date_trunc('{$bucket}', started_at) as bucket")
            ->selectRaw('count(*) as opens')
            ->selectRaw('count(distinct user_id) as unique_users')
            ->groupBy(DB::raw("date_trunc('{$bucket}', started_at)"))
            ->orderBy('bucket')
            ->get();

        $labels = $rows->pluck('bucket')->map(fn ($d) => Carbon::parse($d)->format($format))->all();

        return [
            'datasets' => [
                ['label' => 'App opens', 'data' => $rows->pluck('opens')->all(), 'borderColor' => '#22d3ee'],
                ['label' => 'Unique users', 'data' => $rows->pluck('unique_users')->all(), 'borderColor' => '#f472b6'],
            ],
            'labels' => $labels,
        ];
    }
}
