<?php

namespace App\Filament\Resources\RestaurantSubmissions\Tables;

use App\Filament\Resources\RestaurantSubmissions\SubmissionActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RestaurantSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'university']))
            ->columns([
                TextColumn::make('submission_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'changes_requested' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('user.email')
                    ->label('Submitted by')
                    ->searchable(),
                TextColumn::make('university.short_name')
                    ->label('University'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'changes_requested' => 'Changes requested',
                    ])
                    ->default('pending'),
            ])
            ->recordActions([
                SubmissionActions::approve(),
                SubmissionActions::reject(),
                SubmissionActions::requestChanges(),
                SubmissionActions::link(),
            ]);
    }
}
