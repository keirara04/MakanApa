<?php

namespace App\Filament\Resources\RestaurantSubmissions;

use App\Filament\Resources\RestaurantSubmissions\Pages\ListRestaurantSubmissions;
use App\Filament\Resources\RestaurantSubmissions\Pages\ViewRestaurantSubmission;
use App\Filament\Resources\RestaurantSubmissions\Schemas\RestaurantSubmissionInfolist;
use App\Filament\Resources\RestaurantSubmissions\Tables\RestaurantSubmissionsTable;
use App\Models\RestaurantSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Mostly read-only presentation, not a generic CRUD form — a submission's fields are a
 * moderation record, not something an admin hand-edits. All state transitions go through
 * RestaurantSubmissionModerationService via the header actions on the View page (and the
 * table's row actions), never a raw form save.
 */
class RestaurantSubmissionResource extends Resource
{
    protected static ?string $model = RestaurantSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return RestaurantSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestaurantSubmissionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurantSubmissions::route('/'),
            'view' => ViewRestaurantSubmission::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'pending')->count();
    }
}
