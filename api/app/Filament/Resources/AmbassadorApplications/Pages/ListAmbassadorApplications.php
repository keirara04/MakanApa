<?php

namespace App\Filament\Resources\AmbassadorApplications\Pages;

use App\Filament\Resources\AmbassadorApplications\AmbassadorApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListAmbassadorApplications extends ListRecords
{
    protected static string $resource = AmbassadorApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
