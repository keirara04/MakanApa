<?php

namespace App\Filament\Resources\Universities;

use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Models\University;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** No delete action — universities are referenced by affiliations/submissions/decisions; use "active" to retire one. */
class UniversityResource extends Resource
{
    protected static ?string $model = University::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('short_name')->required(),
                TextInput::make('country'),
                TextInput::make('latitude')->numeric(),
                TextInput::make('longitude')->numeric(),
                Toggle::make('active')->required()->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('short_name')->searchable(),
                TextColumn::make('country')->searchable(),
                IconColumn::make('active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUniversities::route('/'),
        ];
    }
}
