<?php

namespace App\Filament\Resources\AccountDeletions\Pages;

use App\Filament\Resources\AccountDeletions\AccountDeletionResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountDeletions extends ListRecords
{
    protected static string $resource = AccountDeletionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
