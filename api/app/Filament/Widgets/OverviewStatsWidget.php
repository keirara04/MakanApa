<?php

namespace App\Filament\Widgets;

use App\Models\Restaurant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $weeklyActiveUsers = User::whereHas(
            'tokens',
            fn ($query) => $query->where('last_used_at', '>=', now()->subDays(7))
        )->count();

        return [
            Stat::make('Active restaurants', Restaurant::where('is_active', true)->count()),
            Stat::make('Total users', User::registered()->count()),
            Stat::make('Guests', User::where('is_guest', true)->count())
                ->description('Anonymous app accounts not yet signed up'),
            Stat::make('Weekly active users', $weeklyActiveUsers)
                ->description('Users whose API token was used in the last 7 days'),
            $this->guestConversionStat(),
        ];
    }

    /**
     * Of the guests created in the last 30 days, how many went on to make a real account — and
     * which app surface (`signup_source`) converted them most.
     */
    private function guestConversionStat(): Stat
    {
        $since = now()->subDays(30);

        $cohort = User::query()
            ->where('created_at', '>=', $since)
            ->where(fn ($query) => $query->where('is_guest', true)->orWhereNotNull('upgraded_from_guest_at'));
        $guests = (clone $cohort)->count();
        $converted = (clone $cohort)->whereNotNull('upgraded_from_guest_at')->count();

        $topSources = User::query()
            ->where('upgraded_from_guest_at', '>=', $since)
            ->selectRaw("coalesce(signup_source, 'unknown') as source, count(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(2)
            ->pluck('total', 'source')
            ->map(fn ($total, $source) => "{$source} ({$total})")
            ->implode(', ');

        $rate = $guests > 0 ? round($converted / $guests * 100, 1) : 0;

        return Stat::make('Guest → account (30d)', "{$rate}%")
            ->description("{$converted} of {$guests} new guests".($topSources !== '' ? " · top: {$topSources}" : ''));
    }
}
