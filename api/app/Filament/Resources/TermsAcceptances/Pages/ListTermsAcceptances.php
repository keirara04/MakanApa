<?php

namespace App\Filament\Resources\TermsAcceptances\Pages;

use App\Filament\Resources\TermsAcceptances\TermsAcceptanceResource;
use Filament\Resources\Pages\ListRecords;

class ListTermsAcceptances extends ListRecords
{
    protected static string $resource = TermsAcceptanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
