<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Filament\Resources\Restaurants\HalalActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HalalCertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'halalCertificates';

    protected static ?string $title = 'Halal certificates';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'desc')
            ->columns([
                TextColumn::make('authority')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('certificate_number'),
                TextColumn::make('expires_at')->date(),
                TextColumn::make('status')->badge()->state(fn ($record) => $record->isExpired() && $record->status->value === 'valid' ? 'expired' : $record->status->value),
                TextColumn::make('verification_method')->label('Verified via')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('registry_checked_at')->label('Directory check')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                HalalActions::registryCheck(),
                HalalActions::revoke(),
            ]);
    }
}
