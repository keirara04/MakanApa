<?php

namespace App\Filament\Resources\UserAffiliations;

use App\Filament\Resources\UserAffiliations\Pages\ListUserAffiliations;
use App\Filament\Resources\UserAffiliations\Tables\UserAffiliationsTable;
use App\Models\UserAffiliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserAffiliationResource extends Resource
{
    protected static ?string $model = UserAffiliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function table(Table $table): Table
    {
        return UserAffiliationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserAffiliations::route('/'),
        ];
    }
}
