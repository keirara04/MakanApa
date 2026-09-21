<?php

namespace App\Filament\Resources\RestaurantPhotos;

use App\Filament\Resources\RestaurantPhotos\Pages\ListRestaurantPhotos;
use App\Filament\Resources\RestaurantPhotos\Tables\RestaurantPhotosTable;
use App\Models\RestaurantPhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Cross-restaurant moderation queue — the RelationManager on RestaurantResource covers "photos
 * for this one restaurant"; this covers "what's been uploaded recently across the whole app,"
 * which is what actually needs reviewing day to day. Same Hide/Restore actions, same is_active
 * column, no separate schema.
 */
class RestaurantPhotoResource extends Resource
{
    protected static ?string $model = RestaurantPhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Photos';

    public static function table(Table $table): Table
    {
        return RestaurantPhotosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurantPhotos::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
