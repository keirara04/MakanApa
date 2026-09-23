<?php

namespace App\Filament\Widgets;

use App\Models\MarketingEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketingFunnelWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Landing page (last 7 days)';

    protected function getStats(): array
    {
        $since = now()->subDays(7);

        $views = MarketingEvent::where('event', MarketingEvent::LANDING_VIEW)
            ->where('created_at', '>=', $since)
            ->count();

        $clicksBySource = MarketingEvent::where('event', MarketingEvent::TESTFLIGHT_CLICK)
            ->where('created_at', '>=', $since)
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $clicks = $clicksBySource->sum();

        $breakdown = $clicksBySource
            ->sortDesc()
            ->map(fn ($total, $source) => ($source ?: 'other').' '.$total)
            ->implode(' · ');

        return [
            Stat::make('Landing views', $views),
            Stat::make('TestFlight clicks', $clicks)
                ->description($breakdown ?: 'No clicks yet'),
            Stat::make('Click rate', $views > 0 ? round($clicks / $views * 100, 1).'%' : '–')
                ->description('Clicks ÷ views'),
        ];
    }
}
