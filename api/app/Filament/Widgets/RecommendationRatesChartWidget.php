<?php

namespace App\Filament\Widgets;

use App\Models\DecisionRecommendation;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Accept rate = accepted_at set / shown_at set. Reroll rate = rejected_at set / shown_at set
 * (RecommendationController::reroll() sets rejected_at on the row being rerolled away from —
 * there's no separate "reroll" event to count, a reroll IS a rejection). Grouped by the date a
 * recommendation was shown, last 14 days.
 */
class RecommendationRatesChartWidget extends LineChartWidget
{
    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return 'Recommendation accept / reroll rate (last 14 days)';
    }

    protected function getData(): array
    {
        $rows = DecisionRecommendation::query()
            ->whereNotNull('shown_at')
            ->where('shown_at', '>=', now()->subDays(14))
            ->selectRaw('date(shown_at) as day')
            ->selectRaw('count(*) as shown')
            ->selectRaw('count(accepted_at) as accepted')
            ->selectRaw('count(rejected_at) as rejected')
            ->groupBy(DB::raw('date(shown_at)'))
            ->orderBy('day')
            ->get();

        $labels = $rows->pluck('day')->map(fn ($d) => Carbon::parse($d)->format('M j'))->all();
        $acceptRate = $rows->map(fn ($r) => $r->shown > 0 ? round(100 * $r->accepted / $r->shown, 1) : 0)->all();
        $rerollRate = $rows->map(fn ($r) => $r->shown > 0 ? round(100 * $r->rejected / $r->shown, 1) : 0)->all();

        return [
            'datasets' => [
                ['label' => 'Accept rate %', 'data' => $acceptRate, 'borderColor' => '#22c55e'],
                ['label' => 'Reroll rate %', 'data' => $rerollRate, 'borderColor' => '#f59e0b'],
            ],
            'labels' => $labels,
        ];
    }
}
