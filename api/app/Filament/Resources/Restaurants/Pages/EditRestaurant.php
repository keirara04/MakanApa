<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\HalalActions;
use App\Filament\Resources\Restaurants\RestaurantActions;
use App\Filament\Resources\Restaurants\RestaurantResource;
use Filament\Resources\Pages\EditRecord;

class EditRestaurant extends EditRecord
{
    protected static string $resource = RestaurantResource::class;

    // No DeleteAction — restaurants are never hard-deleted, only deactivated or merged.
    protected function getHeaderActions(): array
    {
        return [
            RestaurantActions::deactivate(),
            RestaurantActions::reopen(),
            RestaurantActions::merge(),
            HalalActions::override(),
        ];
    }
}
