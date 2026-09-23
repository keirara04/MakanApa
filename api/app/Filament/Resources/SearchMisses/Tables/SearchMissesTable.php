<?php

namespace App\Filament\Resources\SearchMisses\Tables;

use App\Models\SearchMiss;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SearchMissesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->columns([
                TextColumn::make('query')->searchable(),
                TextColumn::make('hits')->numeric()->sortable(),
                TextColumn::make('area')
                    ->label('Area (~1 km)')
                    ->state(fn (SearchMiss $miss) => "{$miss->latitude_cell}, {$miss->longitude_cell}")
                    ->url(fn (SearchMiss $miss) => "https://www.google.com/maps/search/?api=1&query={$miss->latitude_cell},{$miss->longitude_cell}", shouldOpenInNewTab: true),
                TextColumn::make('last_seen_at')->since()->sortable(),
            ]);
    }
}
