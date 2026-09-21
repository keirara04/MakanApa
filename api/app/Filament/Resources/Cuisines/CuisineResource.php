<?php

namespace App\Filament\Resources\Cuisines;

use App\Filament\Resources\Cuisines\Pages\ManageCuisines;
use App\Models\Cuisine;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * No delete action — restaurants reference cuisines via a pivot with no soft-retire column of
 * its own; treat this as an append-only lookup table for v1 rather than adding one just for this.
 */
class CuisineResource extends Resource
{
    protected static ?string $model = Cuisine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Places';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('slug')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCuisines::route('/'),
        ];
    }
}
