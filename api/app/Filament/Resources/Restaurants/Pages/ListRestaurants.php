<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Support\Halal\HalalStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRestaurants extends ListRecords
{
    protected static string $resource = RestaurantResource::class;

    /** Fewer Google reviews than this and the rating isn't worth much. */
    public const FEW_REVIEWS = 10;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Data-quality to-do lists — every tab but "All" is active places only, since a closed or
     * merged place's gaps don't matter to anyone.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            // Google photos are fetched live per request (Places terms forbid storing them), so
            // this is "no community photo of our own" — what a Google outage or a manual place
            // would show nothing for.
            'no_photos' => Tab::make('No community photos')
                ->modifyQueryUsing(fn (Builder $query) => $this->active($query)
                    ->whereDoesntHave('photos', fn (Builder $photos) => $photos->where('is_active', true)->where('disk', config('restaurant_photos.public_disk')))),
            // No weekly periods means open/closed falls back to a stale snapshot or "unknown".
            'no_hours' => Tab::make('Missing opening hours')
                ->modifyQueryUsing(fn (Builder $query) => $this->active($query)
                    ->whereRaw("(case when jsonb_typeof(opening_hours->'periods') = 'array' then jsonb_array_length(opening_hours->'periods') else 0 end) = 0")),
            'halal_unknown' => Tab::make('Halal status unknown')
                ->modifyQueryUsing(fn (Builder $query) => $this->active($query)->where('halal_status', HalalStatus::Unknown)),
            'few_reviews' => Tab::make('No rating / few reviews')
                ->modifyQueryUsing(fn (Builder $query) => $this->active($query)
                    ->where(fn (Builder $q) => $q->whereNull('rating')->orWhereNull('user_rating_count')->orWhere('user_rating_count', '<', self::FEW_REVIEWS))),
        ];
    }

    private function active(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('merged_into_restaurant_id');
    }
}
