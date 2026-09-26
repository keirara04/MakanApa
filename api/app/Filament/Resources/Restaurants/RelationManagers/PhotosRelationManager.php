<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Filament\Resources\RestaurantPhotos\RestaurantPhotoActions;
use App\Models\RestaurantPhoto;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * No create/edit form — photos only ever arrive via uploads (RestaurantPhotoPromotionService).
 * Moderation here is Hide/Restore only (shared with RestaurantPhotoResource's global queue),
 * toggling the existing `is_active` column — never a hard delete of a photo row.
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
            ->modifyQueryUsing(fn ($query) => $query->with('uploadedBy:id,email'))
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
                RestaurantPhotoActions::hide(),
                RestaurantPhotoActions::restore(),
            ]);
    }
}
