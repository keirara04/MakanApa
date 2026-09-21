<?php

namespace App\Filament\Resources\UserAffiliations\Pages;

use App\Filament\Resources\UserAffiliations\UserAffiliationResource;
use Filament\Resources\Pages\ListRecords;

class ListUserAffiliations extends ListRecords
{
    protected static string $resource = UserAffiliationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
