<?php

namespace App\Filament\Widgets;

use App\Models\RestaurantSave;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SavesTrendChartWidget extends LineChartWidget
{
    protected static ?int $sort = 4;

    /** Refreshed on page load, not every 5s (Filament's default). */
    protected ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return 'Restaurant saves (last 14 days)';
    }

    protected function getData(): array
    {
        return Cache::remember('admin-widget:saves-trend', now()->addMinutes(5), fn (): array => $this->buildData());
    }

    /** @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>} */
    private function buildData(): array
    {
        $rows = RestaurantSave::query()
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('date(created_at) as day')
            ->selectRaw('count(*) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->orderBy('day')
            ->get();

        return [
            'datasets' => [
                ['label' => 'Saves', 'data' => $rows->pluck('total')->all(), 'borderColor' => '#3b82f6'],
            ],
            'labels' => $rows->pluck('day')->map(fn ($d) => Carbon::parse($d)->format('M j'))->all(),
        ];
    }
}
