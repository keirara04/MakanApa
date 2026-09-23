<?php

namespace App\Filament\Resources\SearchMisses\Pages;

use App\Filament\Resources\SearchMisses\SearchMissResource;
use Filament\Resources\Pages\ListRecords;

class ListSearchMisses extends ListRecords
{
    protected static string $resource = SearchMissResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
