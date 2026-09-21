<?php

namespace App\Filament\Resources\RestaurantSubmissions\Pages;

use App\Filament\Resources\RestaurantSubmissions\RestaurantSubmissionResource;
use App\Filament\Resources\RestaurantSubmissions\SubmissionActions;
use Filament\Resources\Pages\ViewRecord;

class ViewRestaurantSubmission extends ViewRecord
{
    protected static string $resource = RestaurantSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubmissionActions::requestChanges(),
            SubmissionActions::reject(),
            SubmissionActions::link(),
            SubmissionActions::approve(),
        ];
    }
}
