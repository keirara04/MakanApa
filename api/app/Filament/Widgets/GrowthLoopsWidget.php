<?php

namespace App\Filament\Widgets;

use App\Models\MarketingEvent;
use App\Models\MealNudge;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/** Share funnel (acquisition) and mealtime-nudge funnel (retention), last 7 days. */
class GrowthLoopsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Sharing & mealtime nudges (last 7 days)';

    /** Refreshed on page load, not every 5s (Filament's default). */
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        ['share' => $share, 'sent' => $sent, 'opened' => $opened, 'acted' => $acted] = Cache::remember(
            'admin-widget:growth-loops',
            now()->addMinutes(2),
            function (): array {
                $since = now()->subDays(7);

                $nudges = MealNudge::where('sent_at', '>=', $since)->where('status', MealNudge::STATUS_SENT);

                return [
                    'share' => MarketingEvent::whereIn('event', [
                        MarketingEvent::SHARE_STARTED, MarketingEvent::SHARE_VIEW, MarketingEvent::SHARE_OPEN_APP, MarketingEvent::SHARE_GET_APP,
                    ])->where('created_at', '>=', $since)->selectRaw('event, count(*) as total')->groupBy('event')->pluck('total', 'event')->all(),
                    'sent' => (clone $nudges)->count(),
                    'opened' => (clone $nudges)->whereNotNull('opened_at')->count(),
                    'acted' => (clone $nudges)->whereNotNull('acted_at')->count(),
                ];
            },
        );

        $rate = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100).'%' : '–';

        return [
            Stat::make('Shares started', (int) ($share[MarketingEvent::SHARE_STARTED] ?? 0))
                ->description(sprintf(
                    '%d viewed · %d opened app · %d got app',
                    $share[MarketingEvent::SHARE_VIEW] ?? 0, $share[MarketingEvent::SHARE_OPEN_APP] ?? 0, $share[MarketingEvent::SHARE_GET_APP] ?? 0,
                )),
            Stat::make('Nudges sent', $sent)
                ->description("{$rate($opened, $sent)} opened"),
            Stat::make('Nudges → a decision', $rate($acted, $sent))
                ->description("{$acted} led to Makan sini or a quick pick"),
        ];
    }
}
