<?php

namespace App\Filament\Resources\Restaurants;

use App\Filament\Resources\Restaurants\Pages\CreateRestaurant;
use App\Filament\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\Resources\Restaurants\RelationManagers\FieldOverridesRelationManager;
use App\Filament\Resources\Restaurants\RelationManagers\HalalCertificatesRelationManager;
use App\Filament\Resources\Restaurants\RelationManagers\HalalVerificationsRelationManager;
use App\Filament\Resources\Restaurants\RelationManagers\MenuItemsRelationManager;
use App\Filament\Resources\Restaurants\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Restaurants\Schemas\RestaurantForm;
use App\Filament\Resources\Restaurants\Tables\RestaurantsTable;
use App\Models\Restaurant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Places';

    public static function form(Schema $schema): Schema
    {
        return RestaurantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestaurantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MenuItemsRelationManager::class,
            PhotosRelationManager::class,
            FieldOverridesRelationManager::class,
            HalalVerificationsRelationManager::class,
            HalalCertificatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurants::route('/'),
            'create' => CreateRestaurant::route('/create'),
            'edit' => EditRestaurant::route('/{record}/edit'),
        ];
    }
}
