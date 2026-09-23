<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only halal ledger for one restaurant, newest first — the supersession chain answers
 * "why does it say this now, and what did it say before". Rows are only ever written by
 * HalalVerificationService.
 */
class HalalVerificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'halalVerifications';

    protected static ?string $title = 'Halal history';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['moderator:id,email', 'certificate:id,authority,certificate_number,expires_at,status']))
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('state')->badge()->color(fn ($state) => $state->value === 'active' ? 'success' : 'gray'),
                TextColumn::make('decision_method')->label('Method')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('evidence_source')->label('Evidence')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('certificate.certificate_number')->label('Certificate')->placeholder('—')
                    ->description(fn ($record) => $record->certificate ? $record->certificate->authority->label().' · exp '.$record->certificate->expires_at?->toDateString() : null),
                TextColumn::make('moderator.email')->label('By')->placeholder('—'),
                TextColumn::make('submission_id')->label('Report #')->placeholder('—'),
                TextColumn::make('override_reason')->label('Override reason')->placeholder('—')->wrap()->toggleable(),
                TextColumn::make('heuristic_matches')->label('Heuristic match')->placeholder('—')->toggleable()
                    ->formatStateUsing(fn ($state) => is_array($state) ? collect($state)->map(fn ($m) => "{$m['field']}: {$m['term']} ({$m['strength']})")->join(', ') : (string) $state),
                TextColumn::make('effective_from')->dateTime(),
                TextColumn::make('effective_until')->dateTime()->placeholder('—'),
                TextColumn::make('superseded_by_id')->label('Superseded by')->placeholder('—'),
            ]);
    }
}
