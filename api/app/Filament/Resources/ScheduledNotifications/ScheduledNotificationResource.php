<?php

namespace App\Filament\Resources\ScheduledNotifications;

use App\Filament\Resources\ScheduledNotifications\Pages\CreateScheduledNotification;
use App\Filament\Resources\ScheduledNotifications\Pages\EditScheduledNotification;
use App\Filament\Resources\ScheduledNotifications\Pages\ListScheduledNotifications;
use App\Filament\Resources\ScheduledNotifications\Schemas\ScheduledNotificationForm;
use App\Filament\Resources\ScheduledNotifications\Tables\ScheduledNotificationsTable;
use App\Models\ScheduledNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * "Add/edit/remove a scheduled push notification" — sibling to the immediate-send
 * BroadcastNotification page. A row here is just data; app/Console/Commands/
 * DispatchScheduledNotifications.php (run every minute by the scheduler) is what actually
 * sends it once send_at arrives, via the same NotificationBroadcastService BroadcastNotification
 * uses. Editing/cancelling a pending row is safe because that command re-reads status fresh —
 * nothing here talks to the queue directly.
 */
class ScheduledNotificationResource extends Resource
{
    protected static ?string $model = ScheduledNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Scheduled Notifications';

    public static function form(Schema $schema): Schema
    {
        return ScheduledNotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduledNotificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledNotifications::route('/'),
            'create' => CreateScheduledNotification::route('/create'),
            'edit' => EditScheduledNotification::route('/{record}/edit'),
        ];
    }
}
