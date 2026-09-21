<?php

namespace App\Filament\Resources\Restaurants\Tables;

use App\Filament\Resources\Restaurants\RestaurantActions;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RestaurantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('food_category')->label('Category')->searchable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('provider')->badge(),
                TextColumn::make('rating')->numeric()->sortable(),
                TextColumn::make('mergedInto.name')->label('Merged into')->placeholder('—'),
                TextColumn::make('last_synced_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
                RestaurantActions::deactivate(),
                RestaurantActions::reopen(),
            ]);
    }
}
