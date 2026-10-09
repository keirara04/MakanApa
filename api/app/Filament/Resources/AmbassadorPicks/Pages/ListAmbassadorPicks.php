<?php

namespace App\Filament\Resources\AmbassadorPicks\Pages;

use App\Filament\Resources\AmbassadorPicks\AmbassadorPickResource;
use Filament\Resources\Pages\ListRecords;

class ListAmbassadorPicks extends ListRecords
{
    protected static string $resource = AmbassadorPickResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
