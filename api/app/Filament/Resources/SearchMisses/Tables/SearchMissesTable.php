<?php

namespace App\Filament\Resources\SearchMisses\Tables;

use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Models\SearchMiss;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('resolved_at')->since()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'resolved' => 'Resolved'])
                    ->default('open')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'open' => $query->open(),
                        'resolved' => $query->whereNotNull('resolved_at')->whereColumn('last_seen_at', '<=', 'resolved_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                // The create form starts at the ~1 km cell centre — drag/fix the pin there.
                Action::make('createPlace')
                    ->label('Create place')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (SearchMiss $miss) => RestaurantResource::getUrl('create', [
                        'name' => $miss->query,
                        'latitude' => (string) $miss->latitude_cell,
                        'longitude' => (string) $miss->longitude_cell,
                    ])),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (SearchMiss $miss) => $miss->isOpen())
                    ->action(fn (SearchMiss $miss) => $miss->update(['resolved_at' => now(), 'resolved_by' => auth()->id()])),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (SearchMiss $miss) => ! $miss->isOpen())
                    ->action(fn (SearchMiss $miss) => $miss->update(['resolved_at' => null, 'resolved_by' => null])),
            ]);
    }
}
