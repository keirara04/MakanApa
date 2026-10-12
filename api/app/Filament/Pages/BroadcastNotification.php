<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\AccountAdminNotice;
use App\Notifications\ReleaseAnnouncement;
use App\Services\NotificationBroadcastService;
use App\Support\NotificationCategory;
use Filament\Actions\Action;
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
 * implementation of "send a push to everyone." Queued, so sending never blocks the page.
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

    /**
     * The only way the form sends: a confirm step naming how many people it reaches, so a stray
     * Enter in the Title field can't push to everyone.
     */
    public function sendAction(): Action
    {
        return Action::make('send')
            ->label('Send broadcast')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalHeading('Send this push now?')
            ->modalDescription(fn (): string => sprintf(
                'It goes to %d %s with this category on and notifications set up. It cannot be recalled.',
                $count = $this->reachableCount(), $count === 1 ? 'person' : 'people',
            ))
            ->modalSubmitActionLabel('Send now')
            ->action(fn (NotificationBroadcastService $service) => $this->send($service));
    }

    /** Users who'd get it: category on (or on by default) and an iPhone token or web push subscription. */
    private function reachableCount(): int
    {
        $category = (string) ($this->data['category'] ?? 'account_admin');

        return User::query()
            ->whereRaw('coalesce((notification_preferences->>?)::boolean, ?)', [$category, in_array($category, NotificationCategory::DEFAULT_TRUE, true)])
            ->where(fn ($query) => $query->whereHas('deviceTokens')->orWhereHas('pushSubscriptions'))
            ->count();
    }

    public function send(NotificationBroadcastService $service): void
    {
        $data = $this->form->getState();

        $notification = $data['category'] === 'release_announcements'
            ? new ReleaseAnnouncement($data['version'], $data['message'], $data['appStoreUrl'] ?: null)
            : new AccountAdminNotice($data['title'], $data['body']);

        $service->queue($notification, [
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
            ->title('Broadcast queued')
            ->body('It sends in the background — watch the Notification Log for delivery. Opted-out users and devices without a valid token are skipped automatically.')
            ->success()
            ->send();

        $this->form->fill(['category' => $data['category']]);
    }
}
