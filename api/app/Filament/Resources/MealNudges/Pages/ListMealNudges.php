<?php

namespace App\Filament\Resources\MealNudges\Pages;

use App\Filament\Resources\MealNudges\MealNudgeResource;
use Filament\Resources\Pages\ListRecords;

class ListMealNudges extends ListRecords
{
    protected static string $resource = MealNudgeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
