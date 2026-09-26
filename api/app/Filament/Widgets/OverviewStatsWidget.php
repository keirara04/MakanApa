<?php

namespace App\Filament\Widgets;

use App\Models\Restaurant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OverviewStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /** Refreshed on page load, not every 5s (Filament's default). */
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $counts = Cache::remember('admin-widget:overview-stats', now()->addMinutes(2), fn (): array => [
            'activeRestaurants' => Restaurant::where('is_active', true)->count(),
            'registeredUsers' => User::registered()->count(),
            'guests' => User::where('is_guest', true)->count(),
            'weeklyActiveUsers' => User::whereHas(
                'tokens',
                fn ($query) => $query->where('last_used_at', '>=', now()->subDays(7))
            )->count(),
            'guestConversion' => $this->guestConversion(),
        ]);
        ['guests' => $newGuests, 'converted' => $converted, 'topSources' => $topSources] = $counts['guestConversion'];
        $rate = $newGuests > 0 ? round($converted / $newGuests * 100, 1) : 0;

        return [
            Stat::make('Active restaurants', $counts['activeRestaurants']),
            Stat::make('Total users', $counts['registeredUsers']),
            Stat::make('Guests', $counts['guests'])
                ->description('Anonymous app accounts not yet signed up'),
            Stat::make('Weekly active users', $counts['weeklyActiveUsers'])
                ->description('Users whose API token was used in the last 7 days'),
            Stat::make('Guest → account (30d)', "{$rate}%")
                ->description("{$converted} of {$newGuests} new guests".($topSources !== '' ? " · top: {$topSources}" : '')),
        ];
    }

    /**
     * Of the guests created in the last 30 days, how many went on to make a real account — and
     * which app surface (`signup_source`) converted them most.
     *
     * @return array{guests: int, converted: int, topSources: string}
     */
    private function guestConversion(): array
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

        return ['guests' => $guests, 'converted' => $converted, 'topSources' => $topSources];
    }
}
