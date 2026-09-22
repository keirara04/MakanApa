<?php

namespace App\Filament\Resources\ScheduledNotifications\Pages;

use App\Filament\Resources\ScheduledNotifications\ScheduledNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduledNotifications extends ListRecords
{
    protected static string $resource = ScheduledNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
