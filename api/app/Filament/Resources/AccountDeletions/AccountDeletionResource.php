<?php

namespace App\Filament\Resources\AccountDeletions;

use App\Filament\Resources\AccountDeletions\Pages\ListAccountDeletions;
use App\Filament\Resources\AccountDeletions\Tables\AccountDeletionsTable;
use App\Models\AccountDeletion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only. Account deletion is instant self-service (AuthController::destroy()) — there's no
 * request to approve, this is purely the trace left behind after the user row is gone.
 */
class AccountDeletionResource extends Resource
{
    protected static ?string $model = AccountDeletion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    public static function table(Table $table): Table
    {
        return AccountDeletionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountDeletions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
