<?php

namespace App\Filament\Resources\CommunityRequests\Pages;

use App\Filament\Resources\CommunityRequests\CommunityRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityRequests extends ListRecords
{
    protected static string $resource = CommunityRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
