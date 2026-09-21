<?php

namespace App\Filament\Resources\Cuisines\Pages;

use App\Filament\Resources\Cuisines\CuisineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCuisines extends ManageRecords
{
    protected static string $resource = CuisineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
