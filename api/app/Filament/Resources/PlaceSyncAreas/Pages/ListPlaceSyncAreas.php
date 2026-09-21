<?php

namespace App\Filament\Resources\PlaceSyncAreas\Pages;

use App\Filament\Resources\PlaceSyncAreas\PlaceSyncAreaResource;
use Filament\Resources\Pages\ListRecords;

class ListPlaceSyncAreas extends ListRecords
{
    protected static string $resource = PlaceSyncAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
