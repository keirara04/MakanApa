<?php

namespace App\Filament\Widgets;

use App\Models\CommunityRequest;
use App\Models\RestaurantSubmission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The admin homepage's priority: what's waiting on a human, not vanity metrics. No third
 * "failed moderation operations" card — there's no real failure state to count yet, so it
 * isn't manufactured just to fill a slot.
 */
class NeedsAttentionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Needs attention';

    protected function getStats(): array
    {
        return [
            Stat::make('Pending submissions', RestaurantSubmission::where('status', 'pending')->count())
                ->description('Restaurant submissions awaiting moderation')
                ->color('warning'),
            Stat::make('Community requests', CommunityRequest::where('status', 'pending')->count())
                ->description('Missing university/area requests awaiting review')
                ->color('warning'),
        ];
    }
}
