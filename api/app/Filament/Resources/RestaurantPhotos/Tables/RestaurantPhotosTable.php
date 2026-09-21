<?php

namespace App\Filament\Resources\RestaurantPhotos\Tables;

use App\Filament\Resources\RestaurantPhotos\RestaurantPhotoActions;
use App\Models\RestaurantPhoto;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RestaurantPhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['restaurant', 'uploadedBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('path')
                    ->label('Photo')
                    ->getStateUsing(fn (RestaurantPhoto $record) => $record->publicUrl()),
                TextColumn::make('restaurant.name')->searchable(),
                TextColumn::make('photo_type'),
                TextColumn::make('uploadedBy.email')->label('Uploaded by')->placeholder('—'),
                TextColumn::make('is_active')->label('Visible')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Visible' : 'Hidden')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Visible'),
            ])
            ->recordActions([
                RestaurantPhotoActions::hide(),
                RestaurantPhotoActions::restore(),
            ]);
    }
}
