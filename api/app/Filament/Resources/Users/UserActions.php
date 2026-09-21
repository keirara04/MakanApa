<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use App\Services\AdminUserService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Shared between UsersTable's row actions and the Edit page's header actions. Every state
 * change goes through AdminUserService, which also carries the last-superadmin/self-action
 * guards — never a raw role/status field edit.
 */
class UserActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->color('danger')
            ->visible(fn (User $record) => $record->status === 'active')
            ->schema([
                Textarea::make('reason')->required()->maxLength(500),
            ])
            ->requiresConfirmation()
            ->action(function (User $record, array $data) {
                self::guarded(fn () => app(AdminUserService::class)->suspend($record, $data['reason'], auth()->user()));
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivate')
            ->color('success')
            ->visible(fn (User $record) => $record->status === 'suspended')
            ->requiresConfirmation()
            ->action(fn (User $record) => app(AdminUserService::class)->reactivate($record, auth()->user()));
    }

    public static function changeRole(): Action
    {
        return Action::make('changeRole')
            ->label('Change role')
            ->color('gray')
            ->schema([
                Select::make('role')
                    ->options(['user' => 'User', 'superadmin' => 'Superadmin'])
                    ->required(),
            ])
            ->fillForm(fn (User $record) => ['role' => $record->role])
            ->requiresConfirmation()
            ->action(function (User $record, array $data) {
                self::guarded(fn () => app(AdminUserService::class)->changeRole($record, $data['role'], auth()->user()));
            });
    }

    public static function revokeSessions(): Action
    {
        return Action::make('revokeSessions')
            ->label('Revoke sessions')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Signs this user out of every device immediately.')
            ->action(fn (User $record) => app(AdminUserService::class)->revokeSessions($record, auth()->user()));
    }

    /** AdminUserService's guards throw RuntimeException (last superadmin / self-action) — surface as a notification, not a 500. */
    private static function guarded(\Closure $callback): void
    {
        try {
            $callback();
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
