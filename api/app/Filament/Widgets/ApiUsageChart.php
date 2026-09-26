<?php

namespace App\Filament\Widgets;

use App\Models\AiJudgment;
use App\Models\ApiUsageDaily;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/** Daily outbound API calls per provider/SKU (last 30 days). Shown on the API costs page. */
class ApiUsageChart extends BarChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    private const DAYS = 30;

    private const COLORS = ['#f59e0b', '#3b82f6', '#22c55e', '#a855f7', '#ef4444', '#14b8a6', '#64748b'];

    public function getHeading(): string
    {
        return 'API calls per day (last 30 days)';
    }

    protected function getData(): array
    {
        return Cache::remember('admin:api-usage-chart', now()->addMinutes(5), function () {
            $since = today()->subDays(self::DAYS - 1);
            $days = collect(range(0, self::DAYS - 1))->map(fn (int $i) => $since->copy()->addDays($i)->toDateString());

            $google = ApiUsageDaily::query()
                ->where('provider', ApiUsageDaily::PROVIDER_GOOGLE_PLACES)
                ->where('date', '>=', $since->toDateString())
                ->get(['date', 'endpoint', 'calls'])
                ->groupBy('endpoint');
            $labels = (array) Config::get('admin_budgets.google_places.endpoints', []);

            $datasets = $google->keys()->values()->map(function (string $endpoint, int $i) use ($google, $days, $labels) {
                $byDay = $google[$endpoint]->keyBy(fn ($row) => $row->date->toDateString());

                return [
                    'label' => 'Places · '.($labels[$endpoint]['label'] ?? $endpoint),
                    'data' => $days->map(fn ($d) => (int) ($byDay[$d]->calls ?? 0))->all(),
                    'backgroundColor' => self::COLORS[$i % count(self::COLORS)],
                ];
            })->all();

            $ai = AiJudgment::query()
                ->where('created_at', '>=', $since)
                ->where('provider', 'openrouter')
                ->selectRaw('date(created_at) as day, count(*) as total')
                ->groupByRaw('date(created_at)')
                ->pluck('total', 'day');
            $datasets[] = [
                'label' => 'OpenRouter · AI judgments',
                'data' => $days->map(fn ($d) => (int) ($ai[$d] ?? 0))->all(),
                'backgroundColor' => '#0f172a',
            ];

            return [
                'datasets' => $datasets,
                'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M j'))->all(),
            ];
        });
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]];
    }
}
