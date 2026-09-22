<?php

namespace App\Filament\Resources\ScheduledNotifications\Pages;

use App\Filament\Resources\ScheduledNotifications\ScheduledNotificationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduledNotification extends CreateRecord
{
    protected static string $resource = ScheduledNotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'pending';
        $data['created_by'] = auth()->id();

        return $data;
    }
}
