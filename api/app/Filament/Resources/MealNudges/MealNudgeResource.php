<?php

namespace App\Filament\Resources\MealNudges;

use App\Filament\Resources\MealNudges\Pages\ListMealNudges;
use App\Filament\Resources\MealNudges\Tables\MealNudgesTable;
use App\Models\MealNudge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Every mealtime nudge and how far down the funnel it got. Read-only. */
class MealNudgeResource extends Resource
{
    protected static ?string $model = MealNudge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Mealtime nudges';

    public static function table(Table $table): Table
    {
        return MealNudgesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMealNudges::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
