<?php

namespace App\Filament\Resources\ScheduledNotifications\Schemas;

use App\Models\ScheduledNotification;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ScheduledNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')
                ->label('Category')
                ->options([
                    'account_admin' => 'Account / admin notice',
                    'release_announcements' => 'News & release announcement',
                ])
                ->required()
                ->live()
                ->default('account_admin')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),

            DateTimePicker::make('send_at')
                ->label('Send at')
                ->required()
                ->native(false)
                ->minDate(fn (?ScheduledNotification $record) => $record === null ? now() : null)
                ->helperText('Local server time. A pending schedule can be edited any time before this passes — the send picks up the latest content and time automatically.')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),

            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(100)
                ->visible(fn (Get $get) => $get('category') === 'account_admin')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),

            Textarea::make('body')
                ->label('Message')
                ->required()
                ->maxLength(500)
                ->visible(fn (Get $get) => $get('category') === 'account_admin')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),

            TextInput::make('version')
                ->label('Version')
                ->placeholder('1.2.0')
                ->required()
                ->visible(fn (Get $get) => $get('category') === 'release_announcements')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),

            Textarea::make('message')
                ->label('Message')
                ->required()
                ->maxLength(500)
                ->visible(fn (Get $get) => $get('category') === 'release_announcements')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),

            TextInput::make('app_store_url')
                ->label('App Store URL')
                ->url()
                ->helperText('Optional — included in the push payload for deep-linking.')
                ->visible(fn (Get $get) => $get('category') === 'release_announcements')
                ->disabled(fn (?ScheduledNotification $record) => $record !== null && $record->status !== 'pending'),
        ]);
    }
}
