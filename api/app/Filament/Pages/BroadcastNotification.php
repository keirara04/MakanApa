<?php

namespace App\Filament\Pages;

use App\Notifications\AccountAdminNotice;
use App\Notifications\ReleaseAnnouncement;
use App\Services\NotificationBroadcastService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Superadmin-only (the whole panel already gates on User::canAccessPanel()). Reuses the same
 * Notification classes and NotificationBroadcastService as the artisan command and
 * RestaurantSubmissionModerationService — this page is just another caller, not a separate
 * implementation of "send a push to everyone."
 */
class BroadcastNotification extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Broadcast Notification';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected string $view = 'filament.pages.broadcast-notification';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(['category' => 'account_admin']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('category')
                    ->label('Category')
                    ->options([
                        'account_admin' => 'Account / admin notice',
                        'release_announcements' => 'News & release announcement',
                    ])
                    ->required()
                    ->live()
                    ->helperText('Only users who have this category enabled in Settings will actually receive the push.'),

                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(100)
                    ->visible(fn (Get $get) => $get('category') === 'account_admin'),

                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->maxLength(500)
                    ->visible(fn (Get $get) => $get('category') === 'account_admin'),

                TextInput::make('version')
                    ->label('Version')
                    ->placeholder('1.2.0')
                    ->required()
                    ->visible(fn (Get $get) => $get('category') === 'release_announcements'),

                Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->maxLength(500)
                    ->visible(fn (Get $get) => $get('category') === 'release_announcements'),

                TextInput::make('appStoreUrl')
                    ->label('App Store URL')
                    ->url()
                    ->helperText('Optional — included in the push payload for deep-linking.')
                    ->visible(fn (Get $get) => $get('category') === 'release_announcements'),
            ]);
    }

    public function send(NotificationBroadcastService $service): void
    {
        $data = $this->form->getState();

        $notification = $data['category'] === 'release_announcements'
            ? new ReleaseAnnouncement($data['version'], $data['message'], $data['appStoreUrl'] ?: null)
            : new AccountAdminNotice($data['title'], $data['body']);

        $considered = $service->broadcast($notification, [
            'category' => $data['category'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'version' => $data['version'] ?? null,
            'message' => $data['message'] ?? null,
            'app_store_url' => $data['appStoreUrl'] ?? null,
            'source' => 'manual',
            'created_by' => auth()->id(),
        ]);

        FilamentNotification::make()
            ->title("Broadcast queued for {$considered} users")
            ->body('Opted-out users and devices without a valid token are skipped automatically.')
            ->success()
            ->send();

        $this->form->fill(['category' => $data['category']]);
    }
}
