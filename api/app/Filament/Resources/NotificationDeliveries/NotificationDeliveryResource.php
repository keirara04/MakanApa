<?php

namespace App\Filament\Resources\NotificationDeliveries;

use App\Filament\Resources\NotificationDeliveries\Pages\ListNotificationDeliveries;
use App\Filament\Resources\NotificationDeliveries\Tables\NotificationDeliveriesTable;
use App\Models\NotificationDelivery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fully read-only — no create/edit/delete anywhere in the UI. One row per user per broadcast
 * (manual admin send, scheduled dispatch, or the release-announcement command), all going
 * through NotificationBroadcastService. Lets a superadmin confirm whether a push actually
 * reached everyone, not just that it was queued — see that service's doc comment for how rows
 * move from queued to sent/failed.
 */
class NotificationDeliveryResource extends Resource
{
    protected static ?string $model = NotificationDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Notification Log';

    public static function table(Table $table): Table
    {
        return NotificationDeliveriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationDeliveries::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
