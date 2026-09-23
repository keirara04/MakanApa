<?php

namespace App\Filament\Resources\Halal\Restaurants;

use App\Filament\Resources\Halal\Restaurants\Pages\ListHalalRestaurants;
use App\Filament\Resources\Restaurants\HalalActions;
use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Models\Restaurant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Halal Trust > Restaurants — status-centric lenses (needs verification, recently changed,
 * heuristic classifications). Editing happens on the Restaurant page (history + override).
 */
class HalalRestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Halal Trust';

    protected static ?string $navigationLabel = 'Restaurants';

    protected static ?string $slug = 'halal/restaurants';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('merged_into_restaurant_id')->where('is_active', true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('activeHalalVerification:id,decision_method,evidence_source,heuristic_matches'))
            ->recordUrl(fn (Restaurant $record) => RestaurantResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('halal_status')->label('Status')->badge()
                    ->state(fn (Restaurant $record) => $record->effectiveHalalStatus()->label()),
                TextColumn::make('halal_review_state')->label('Review')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('activeHalalVerification.decision_method')->label('Decided by')
                    ->formatStateUsing(fn ($state) => $state?->label())->placeholder('—'),
                TextColumn::make('heuristic')->label('Heuristic match')->toggleable()
                    ->state(fn (Restaurant $record) => collect($record->activeHalalVerification?->heuristic_matches ?? [])
                        ->map(fn ($m) => "{$m['field']}: {$m['term']}")->join(', ') ?: null)
                    ->placeholder('—'),
                TextColumn::make('ai_hint')->label('AI pork/alcohol')->toggleable()
                    ->state(fn (Restaurant $record) => isset($record->halal_ai_hint['probability'])
                        ? sprintf('%.2f ±%.2f', $record->halal_ai_hint['probability'], $record->halal_ai_hint['stdDev'] ?? 0)
                        : null)
                    ->placeholder('—'),
                TextColumn::make('halal_open_report_count')->label('Open reports')->sortable(),
                TextColumn::make('impressions_count')->label('Impressions')->sortable(),
                TextColumn::make('halal_verified_at')->label('Changed')->since()->sortable()->placeholder('—'),
            ])
            ->recordActions([HalalActions::override()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListHalalRestaurants::route('/')];
    }
}
