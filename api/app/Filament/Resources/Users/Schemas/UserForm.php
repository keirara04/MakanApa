<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * password is write-only here — never displayed, never prefilled, and dehydrated only when the
 * admin actually types a new one (`dehydrated(fn ($state) => filled($state))` below), so leaving
 * it blank on save never touches the existing hash. remember_token / apple_refresh_token still
 * never appear at all. role/status stay read-only placeholders: every state change goes through
 * the Suspend/Reactivate/Change role/Revoke sessions actions (one path, one audit entry each),
 * never a raw field edit — password is the one exception, audited separately in EditUser::afterSave().
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name'),
                        TextInput::make('email')->label('Email address')->email()->required(),
                        TextInput::make('avatar_url')->url(),
                    ]),

                Section::make('Password')
                    ->description('Leave blank to keep the current password. Setting one revokes all of this user\'s existing sessions.')
                    ->schema([
                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->autocomplete('new-password')
                            ->dehydrated(fn (?string $state) => filled($state)),
                    ]),

                Section::make('Account state')
                    ->description('Use the actions above to change role or status — not editable here.')
                    ->columns(3)
                    ->schema([
                        Placeholder::make('role')->content(fn (User $record) => $record->role),
                        Placeholder::make('status')->content(fn (User $record) => $record->status),
                        Placeholder::make('auth_providers')
                            ->label('Sign-in methods')
                            ->content(fn (User $record) => implode(', ', array_filter([
                                $record->apple_sub ? 'Apple' : null,
                                $record->google_sub ? 'Google' : null,
                                $record->password ? 'Email/password' : null,
                            ])) ?: '—'),
                    ]),
            ]);
    }
}
