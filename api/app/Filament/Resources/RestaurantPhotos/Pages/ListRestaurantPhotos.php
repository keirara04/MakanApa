<?php

namespace App\Filament\Resources\RestaurantPhotos\Pages;

use App\Filament\Resources\RestaurantPhotos\RestaurantPhotoResource;
use Filament\Resources\Pages\ListRecords;

class ListRestaurantPhotos extends ListRecords
{
    protected static string $resource = RestaurantPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
