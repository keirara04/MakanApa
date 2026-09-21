<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * password / remember_token / apple_refresh_token are never rendered here at all — not even
 * disabled — they must never round-trip through the admin panel. role/status are read-only
 * placeholders: every state change goes through the Suspend/Reactivate/Change role/Revoke
 * sessions actions (one path, one audit entry each), never a raw field edit.
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
