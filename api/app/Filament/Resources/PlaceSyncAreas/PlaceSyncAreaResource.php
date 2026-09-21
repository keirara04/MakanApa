<?php

namespace App\Filament\Resources\PlaceSyncAreas;

use App\Filament\Resources\PlaceSyncAreas\Pages\ListPlaceSyncAreas;
use App\Filament\Resources\PlaceSyncAreas\Tables\PlaceSyncAreasTable;
use App\Models\PlaceSyncArea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Read-only — internal cron bookkeeping for the Google Places background sync, not admin-editable. */
class PlaceSyncAreaResource extends Resource
{
    protected static ?string $model = PlaceSyncArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Sync status';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    public static function table(Table $table): Table
    {
        return PlaceSyncAreasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaceSyncAreas::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
