<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserActions;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['ambassadorUniversity', 'ambassadorArea']))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->label('Email address')->searchable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('ambassador')
                    ->label('Ambassador of')
                    ->state(fn (User $record) => $record->ambassadorOf()['name'] ?? null)
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->options(['user' => 'User', 'superadmin' => 'Superadmin']),
                SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended']),
                TernaryFilter::make('ambassador')
                    ->label('Ambassadors')
                    ->queries(
                        true: fn ($query) => $query->where(fn ($q) => $q->whereNotNull('ambassador_university_id')->orWhereNotNull('ambassador_area_id')),
                        false: fn ($query) => $query->whereNull('ambassador_university_id')->whereNull('ambassador_area_id'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                UserActions::suspend(),
                UserActions::reactivate(),
                UserActions::changeRole(),
                UserActions::setAmbassador(),
                UserActions::revokeSessions(),
                UserActions::delete(),
                UserActions::restore(),
            ]);
    }
}
