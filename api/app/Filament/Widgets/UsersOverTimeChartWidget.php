<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** New user signups, grouped by hour/day/week/month — switchable via the filter dropdown. */
class UsersOverTimeChartWidget extends LineChartWidget
{
    protected static ?int $sort = 5;

    public ?string $filter = 'day';

    public function getHeading(): string
    {
        return 'New users';
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

        $rows = User::query()
            ->where('created_at', '>=', $since)
            ->selectRaw("date_trunc('{$bucket}', created_at) as bucket")
            ->selectRaw('count(*) as total')
            ->groupBy(DB::raw("date_trunc('{$bucket}', created_at)"))
            ->orderBy('bucket')
            ->get();

        return [
            'datasets' => [
                ['label' => 'New users', 'data' => $rows->pluck('total')->all(), 'borderColor' => '#a855f7'],
            ],
            'labels' => $rows->pluck('bucket')->map(fn ($d) => Carbon::parse($d)->format($format))->all(),
        ];
    }
}
