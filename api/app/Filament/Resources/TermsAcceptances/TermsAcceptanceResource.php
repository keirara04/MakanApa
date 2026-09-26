<?php

namespace App\Filament\Resources\TermsAcceptances;

use App\Filament\Resources\TermsAcceptances\Pages\ListTermsAcceptances;
use App\Filament\Resources\TermsAcceptances\Tables\TermsAcceptancesTable;
use App\Models\TermsAcceptance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only evidence of who agreed to which Terms / Community Guidelines / Privacy versions and
 * when — what a clickwrap dispute or an App Review 1.2 question needs. Rows are append-only.
 */
class TermsAcceptanceResource extends Resource
{
    protected static ?string $model = TermsAcceptance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?string $navigationLabel = 'Terms acceptances';

    public static function table(Table $table): Table
    {
        return TermsAcceptancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTermsAcceptances::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
