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
        ];
    }
}
