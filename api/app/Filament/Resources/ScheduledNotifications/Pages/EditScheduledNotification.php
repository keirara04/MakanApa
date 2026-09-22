<?php

namespace App\Filament\Resources\ScheduledNotifications\Pages;

use App\Filament\Resources\ScheduledNotifications\ScheduledNotificationResource;
use Filament\Resources\Pages\EditRecord;

class EditScheduledNotification extends EditRecord
{
    protected static string $resource = ScheduledNotificationResource::class;

    // No delete/cancel header actions here — those live as row actions on the list table
    // (ScheduledNotificationsTable) so "cancel" stays reachable without opening the record.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
