<?php

namespace App\Filament\Resources\NotificationDeliveries\Tables;

use App\Models\NotificationBroadcast;
use App\Models\NotificationDelivery;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NotificationDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['broadcast.creator:id,email', 'user:id,email']))
            // One row per user per broadcast, so this is the biggest table in the panel: newest
            // first by primary key, and "Previous / Next" paging that skips the full count(*).
            ->defaultSort('id', 'desc')
            ->paginationMode(PaginationMode::Simple)
            ->columns([
                TextColumn::make('created_at')->label('Queued at')->dateTime()->sortable(),
                TextColumn::make('broadcast.category')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'release_announcements' ? 'News & release' : 'Account / admin'),
                TextColumn::make('broadcast.summary')
                    ->label('Content')
                    ->state(fn (NotificationDelivery $record) => $record->broadcast->category === 'release_announcements'
                        ? "{$record->broadcast->version}: {$record->broadcast->message}"
                        : "{$record->broadcast->title}: {$record->broadcast->body}")
                    ->limit(60),
                TextColumn::make('user.email')->label('Recipient')->placeholder('(deleted user)'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'queued' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'skipped_preference' => 'Opted out',
                        'skipped_no_token' => 'No device token',
                        default => ucfirst($state),
                    }),
                TextColumn::make('error')->placeholder('—')->limit(60)->toggleable(),
                TextColumn::make('sent_at')->dateTime()->toggleable(),
                TextColumn::make('broadcast.source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('broadcast.creator.email')->label('Sent by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'skipped_preference' => 'Opted out',
                        'skipped_no_token' => 'No device token',
                    ]),
                SelectFilter::make('notification_broadcast_id')
                    ->label('Broadcast')
                    ->options(fn () => NotificationBroadcast::query()
                        ->latest()
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (NotificationBroadcast $broadcast) => [
                            $broadcast->id => $broadcast->created_at->format('Y-m-d H:i').' — '.($broadcast->title ?? $broadcast->version ?? $broadcast->category),
                        ])),
            ]);
    }
}
