<?php

namespace App\Filament\Resources\Halal\Restaurants\Pages;

use App\Filament\Resources\Halal\Restaurants\HalalRestaurantResource;
use App\Support\Halal\HalalDecisionMethod;
use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListHalalRestaurants extends ListRecords
{
    protected static string $resource = HalalRestaurantResource::class;

    public function getTabs(): array
    {
        return [
            // Most-shown unknown places first — verifying these helps the most users.
            'needs' => Tab::make('Needs verification')
                ->modifyQueryUsing(fn ($query) => $query->where('halal_status', HalalStatus::Unknown)->reorder()->orderByDesc('impressions_count')),
            'reverify' => Tab::make('Re-verify')
                ->modifyQueryUsing(fn ($query) => $query->where('halal_review_state', HalalReviewState::ReverifyRequired)),
            'conflicting' => Tab::make('Conflicting evidence')
                ->modifyQueryUsing(fn ($query) => $query->where('halal_review_state', HalalReviewState::ConflictingEvidence)),
            'recent' => Tab::make('Recently changed')
                ->modifyQueryUsing(fn ($query) => $query->whereNotNull('halal_verified_at')->reorder()->orderByDesc('halal_verified_at')),
            // Advisory only: probability >= 0.7 with stable samples. Decide via "Set halal status".
            'ai_flagged' => Tab::make('AI flagged (review)')
                ->modifyQueryUsing(fn ($query) => $query->where('halal_status', HalalStatus::Unknown)
                    ->whereRaw("(halal_ai_hint->>'probability')::float >= 0.7")
                    ->whereRaw("(halal_ai_hint->>'stdDev')::float <= 0.2")
                    ->reorder()->orderByRaw("(halal_ai_hint->>'probability')::float desc")),
            'heuristic' => Tab::make('Heuristic classifications')
                ->modifyQueryUsing(fn ($query) => $query->whereHas('activeHalalVerification', fn ($v) => $v->where('decision_method', HalalDecisionMethod::Automatic))),
        ];
    }
}
