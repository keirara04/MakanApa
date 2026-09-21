<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Models\RestaurantFieldOverride;
use App\Services\RestaurantSubmissionModerationService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only audit trail — the only mutation allowed from here is "Use Google value again",
 * which removes the override entirely (RestaurantSubmissionModerationService::releaseFieldOverride)
 * and hands the field back to the next Google sync. No create/edit/delete on the override rows
 * themselves; overrides are only ever written by the moderation service.
 */
class FieldOverridesRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldOverrides';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('field'),
                TextColumn::make('value')->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state),
                TextColumn::make('authority')->badge(),
                TextColumn::make('verifier.email')->label('Verified by')->placeholder('—'),
                TextColumn::make('verified_at')->dateTime(),
            ])
            ->recordActions([
                Action::make('useGoogleValueAgain')
                    ->label('Use Google value again')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Removes this override — the field goes back under Google\'s control on the next sync.')
                    ->action(fn (RestaurantFieldOverride $record) => app(RestaurantSubmissionModerationService::class)
                        ->releaseFieldOverride($record->restaurant, $record->field, auth()->user())),
            ]);
    }
}
