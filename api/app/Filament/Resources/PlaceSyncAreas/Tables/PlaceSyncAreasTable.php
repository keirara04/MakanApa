<?php

namespace App\Filament\Resources\PlaceSyncAreas\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlaceSyncAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('synced_at', 'desc')
            ->columns([
                TextColumn::make('provider')->badge(),
                TextColumn::make('latitude')->numeric(4),
                TextColumn::make('longitude')->numeric(4),
                TextColumn::make('radius_km')->numeric(2),
                TextColumn::make('types')->badge()->separator(','),
                TextColumn::make('synced_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->color(fn ($state) => $state && $state->lt(now()->subHours(48)) ? 'danger' : null),
            ]);
    }
}
