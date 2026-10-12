<?php

namespace App\Filament\Widgets;

use App\Support\Region;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Of the picks where someone chose a mood or typed a craving (last 30 days), how often anything
 * nearby fit — per campus, else per area, else per region. A low rate means that place's data
 * is thin: confirm places there or have ambassadors add them.
 */
class PickMatchWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Picks that matched what people asked for (30 days)';

    protected function getStats(): array
    {
        $rows = Cache::remember('admin:pick-match-by-place', 600, fn () => DB::table('decisions')
            ->leftJoin('universities', 'universities.id', '=', 'decisions.university_id')
            ->leftJoin('areas', 'areas.id', '=', 'decisions.area_id')
            ->where('decisions.created_at', '>=', now()->subDays(30))
            ->whereNotNull('decisions.intent_matched')
            ->selectRaw('coalesce(universities.short_name, areas.name, '.Region::sqlCase('decisions.latitude', 'decisions.longitude').') as place')
            ->selectRaw('count(*) as picks, sum(case when decisions.intent_matched then 0 else 1 end) as misses')
            ->groupBy('place')
            ->orderByDesc('picks')
            ->limit(6)
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all());

        if ($rows === []) {
            return [Stat::make('No data yet', '—')->description('Fills in as people pick a mood or type a craving')];
        }

        return array_map(function (array $row) {
            $rate = 1 - (int) $row['misses'] / max(1, (int) $row['picks']);

            return Stat::make(Region::LABELS[$row['place']] ?? $row['place'], round($rate * 100).'% matched')
                ->description("{$row['misses']} of {$row['picks']} picks had nothing that fit")
                ->color($rate >= 0.8 ? 'success' : ($rate >= 0.6 ? 'warning' : 'danger'));
        }, $rows);
    }
}
