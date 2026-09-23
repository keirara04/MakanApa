<?php

namespace App\Filament\Resources\Halal\Reports\Pages;

use App\Filament\Resources\Halal\Reports\HalalReportResource;
use App\Support\Halal\HalalReviewState;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListHalalReports extends ListRecords
{
    protected static string $resource = HalalReportResource::class;

    public function getTabs(): array
    {
        return [
            'queue' => Tab::make('Review queue')
                ->modifyQueryUsing(fn ($query) => $query->where('submission_type', 'halal_report')->where('status', 'pending')),
            'conflicting' => Tab::make('Conflicting evidence')
                ->modifyQueryUsing(fn ($query) => $query->where('submission_type', 'halal_report')->where('status', 'pending')
                    ->whereHas('restaurant', fn ($r) => $r->where('halal_review_state', HalalReviewState::ConflictingEvidence))),
            'owners' => Tab::make('Owner claims')
                ->modifyQueryUsing(fn ($query) => $query->where('submission_type', 'owner_claim')->where('status', 'pending')),
            'all' => Tab::make('All'),
        ];
    }
}
