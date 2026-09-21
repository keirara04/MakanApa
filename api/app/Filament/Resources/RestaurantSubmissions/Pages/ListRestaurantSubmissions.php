<?php

namespace App\Filament\Resources\RestaurantSubmissions\Pages;

use App\Filament\Resources\RestaurantSubmissions\RestaurantSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListRestaurantSubmissions extends ListRecords
{
    protected static string $resource = RestaurantSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
