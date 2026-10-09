<?php

namespace App\Filament\Resources\AmbassadorPicks;

use App\Filament\Resources\AmbassadorPicks\Pages\ListAmbassadorPicks;
use App\Filament\Resources\AmbassadorPicks\Tables\AmbassadorPicksTable;
use App\Models\AmbassadorPick;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Read-only list of ambassador picks — the moderation path for a bad note is Remove. */
class AmbassadorPickResource extends Resource
{
    protected static ?string $model = AmbassadorPick::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function table(Table $table): Table
    {
        return AmbassadorPicksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAmbassadorPicks::route('/'),
        ];
    }
}
