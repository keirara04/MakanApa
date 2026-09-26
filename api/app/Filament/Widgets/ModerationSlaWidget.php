<?php

namespace App\Filament\Widgets;

use App\Services\ModerationSlaService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * How long the oldest item in each moderation queue has been waiting — amber past 12 hours, red
 * past the 24 hours the Community Guidelines promise. No polling: it's cached for a minute and
 * refreshes on page load, which is plenty for a queue measured in hours.
 */
class ModerationSlaWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Moderation response time';

    protected function getStats(): array
    {
        $snapshot = Cache::remember('admin:moderation-sla-snapshot', 60, fn () => app(ModerationSlaService::class)->snapshot());

        return collect($snapshot)->map(fn (array $queue) => Stat::make($queue['label'], ModerationSlaService::formatAge($queue['oldest']))
            ->description($queue['open'] === 0 ? 'Nothing waiting' : "{$queue['open']} open · oldest waiting")
            ->color(ModerationSlaService::color($queue['oldest'])))
            ->values()
            ->all();
    }
}
