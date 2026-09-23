<?php

namespace App\Filament\Widgets;

use App\Models\AiJudgment;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Carbon;

/** Daily Judgment System volume + failures, per purpose filter. Shown on the AI Judgments page. */
class AiJudgmentUsageChart extends BarChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = 'all';

    public function getHeading(): string
    {
        return 'AI judgments per day (last 14 days)';
    }

    protected function getFilters(): ?array
    {
        return ['all' => 'All purposes', 'halal_triage' => 'Halal triage', 'non_halal_second_opinion' => 'Non-halal second opinion', 'craving' => 'Craving'];
    }

    protected function getData(): array
    {
        $since = today()->subDays(13);
        $rows = AiJudgment::query()
            ->where('created_at', '>=', $since)
            ->when($this->filter !== 'all', fn ($q) => $q->where('purpose', $this->filter))
            ->selectRaw("date_trunc('day', created_at) as day")
            ->selectRaw("count(*) filter (where status = 'ok') as ok")
            ->selectRaw("count(*) filter (where status <> 'ok') as not_ok")
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->groupByRaw("date_trunc('day', created_at)")
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());

        $days = collect(range(0, 13))->map(fn ($i) => $since->copy()->addDays($i)->toDateString());

        return [
            'datasets' => [
                ['label' => 'OK', 'data' => $days->map(fn ($d) => (int) ($rows[$d]->ok ?? 0))->all(), 'backgroundColor' => '#22c55e'],
                ['label' => 'Failed / skipped', 'data' => $days->map(fn ($d) => (int) ($rows[$d]->not_ok ?? 0))->all(), 'backgroundColor' => '#f97316'],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M j'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]];
    }
}
