<?php

namespace App\Filament\Resources\UserAffiliations\Tables;

use App\Models\UserAffiliation;
use App\Services\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserAffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'university', 'area']))
            ->columns([
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('university.short_name')->placeholder('—'),
                TextColumn::make('area.short_name')->placeholder('—'),
                TextColumn::make('verification_status')->badge(),
                TextColumn::make('verification_method')->placeholder('—'),
                TextColumn::make('verified_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('verification_status')
                    ->options(['unverified' => 'Unverified', 'self_reported' => 'Self-reported', 'verified' => 'Verified']),
            ])
            ->recordActions([
                Action::make('verify')
                    ->color('success')
                    ->visible(fn (UserAffiliation $record) => $record->verification_status !== 'verified')
                    ->requiresConfirmation()
                    ->action(function (UserAffiliation $record) {
                        $record->update(['verification_status' => 'verified', 'verification_method' => 'admin', 'verified_at' => now()]);
                        app(AdminAuditLogger::class)->log(auth()->user(), 'affiliation.verify', $record);
                    }),
            ]);
    }
}
