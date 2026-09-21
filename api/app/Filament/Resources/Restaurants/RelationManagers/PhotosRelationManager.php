<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Models\RestaurantPhoto;
use App\Services\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * No create/edit form — photos only ever arrive via uploads (RestaurantPhotoPromotionService).
 * Moderation here is Hide/Restore only, toggling the existing `is_active` column — never a
 * hard delete of a photo row.
 */
class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('Photo')
                    ->getStateUsing(fn (RestaurantPhoto $record) => $record->publicUrl()),
                TextColumn::make('photo_type'),
                TextColumn::make('uploadedBy.email')->label('Uploaded by')->placeholder('—'),
                TextColumn::make('is_active')->label('Visible')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Visible' : 'Hidden')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->color('danger')
                    ->visible(fn (RestaurantPhoto $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (RestaurantPhoto $record) => $this->setVisibility($record, false)),
                Action::make('restore')
                    ->label('Restore')
                    ->color('success')
                    ->visible(fn (RestaurantPhoto $record) => ! $record->is_active)
                    ->action(fn (RestaurantPhoto $record) => $this->setVisibility($record, true)),
            ]);
    }

    private function setVisibility(RestaurantPhoto $photo, bool $visible): void
    {
        $photo->update(['is_active' => $visible]);

        app(AdminAuditLogger::class)->log(
            auth()->user(),
            $visible ? 'photo.restore' : 'photo.hide',
            $photo,
        );
    }
}
