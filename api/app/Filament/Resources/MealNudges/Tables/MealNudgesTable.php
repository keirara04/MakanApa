<?php

namespace App\Filament\Resources\MealNudges\Tables;

use App\Services\Nudges\NudgeCopyCatalog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MealNudgesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'restaurant']))
            ->defaultSort('sent_at', 'desc')
            ->columns([
                TextColumn::make('sent_at')->dateTime()->sortable(),
                TextColumn::make('user.email')->label('User')->searchable(),
                TextColumn::make('slot')->badge(),
                TextColumn::make('copy_key')->label('Copy')->badge(),
                TextColumn::make('restaurant.name')->label('Named place')->placeholder('Generic'),
                IconColumn::make('opened_at')->label('Opened')->boolean()->getStateUsing(fn ($record) => $record->opened_at !== null),
                TextColumn::make('action')->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('slot')->options(['lunch' => 'Lunch', 'dinner' => 'Dinner', 'iftar' => 'Iftar']),
                SelectFilter::make('copy_key')->options(array_combine(NudgeCopyCatalog::keys(), NudgeCopyCatalog::keys())),
            ]);
    }
}
