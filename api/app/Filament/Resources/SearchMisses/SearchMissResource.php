<?php

namespace App\Filament\Resources\SearchMisses;

use App\Filament\Resources\SearchMisses\Pages\ListSearchMisses;
use App\Filament\Resources\SearchMisses\Tables\SearchMissesTable;
use App\Models\SearchMiss;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Nearby searches that found nothing, most-repeated first — a to-do list of places MakanApa is
 * missing. Aggregate and anonymous (query + ~1 km cell + count). Read-only.
 */
class SearchMissResource extends Resource
{
    protected static ?string $model = SearchMiss::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassMinus;

    protected static string|\UnitEnum|null $navigationGroup = 'Places';

    protected static ?string $navigationLabel = 'Search misses';

    public static function table(Table $table): Table
    {
        return SearchMissesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSearchMisses::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
