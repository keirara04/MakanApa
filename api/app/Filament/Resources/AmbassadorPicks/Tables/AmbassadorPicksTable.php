<?php

namespace App\Filament\Resources\AmbassadorPicks\Tables;

use App\Models\AmbassadorPick;
use App\Services\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AmbassadorPicksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'restaurant', 'university', 'area']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.email')->label('Ambassador')->searchable(),
                TextColumn::make('community')
                    ->state(fn (AmbassadorPick $record) => $record->university?->short_name ?? $record->area?->short_name)
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('restaurant.name')->label('Place')->searchable(),
                TextColumn::make('note')->wrap()->placeholder('—'),
                TextColumn::make('created_at')->label('Picked')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (AmbassadorPick $record) {
                        app(AdminAuditLogger::class)->log(auth()->user(), 'ambassador_pick.remove', $record, metadata: [
                            'user_id' => $record->user_id,
                            'restaurant_id' => $record->restaurant_id,
                            'note' => $record->note,
                        ]);
                        $record->delete();
                    }),
            ]);
    }
}
