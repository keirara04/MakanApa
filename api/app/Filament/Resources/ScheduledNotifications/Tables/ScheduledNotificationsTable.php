<?php

namespace App\Filament\Resources\ScheduledNotifications\Tables;

use App\Models\ScheduledNotification;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduledNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->defaultSort('send_at', 'desc')
            ->columns([
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'release_announcements' ? 'News & release' : 'Account / admin'),
                TextColumn::make('summary')
                    ->label('Content')
                    ->state(fn (ScheduledNotification $record) => $record->category === 'release_announcements'
                        ? "{$record->version}: {$record->message}"
                        : "{$record->title}: {$record->body}")
                    ->limit(60),
                TextColumn::make('send_at')->dateTime()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'cancelled' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('creator.email')->label('Scheduled by')->toggleable(),
                TextColumn::make('sent_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'sent' => 'Sent', 'cancelled' => 'Cancelled'])
                    ->default('pending'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (ScheduledNotification $record) => $record->status === 'pending'),
                Action::make('cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ScheduledNotification $record) => $record->status === 'pending')
                    ->action(fn (ScheduledNotification $record) => $record->update(['status' => 'cancelled'])),
                DeleteAction::make()->visible(fn (ScheduledNotification $record) => $record->status !== 'sent'),
            ]);
    }
}
