<?php

namespace App\Filament\Resources\TermsAcceptances\Tables;

use App\Models\TermsAcceptance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TermsAcceptancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,email,name'))
            // id tracks insertion order (rows are append-only) and is indexed; accepted_at isn't on its own.
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.email')->label('User')->searchable()->placeholder('—'),
                TextColumn::make('terms_version')->label('Terms')->badge(),
                TextColumn::make('guidelines_version')->label('Guidelines')->badge(),
                TextColumn::make('privacy_version')->label('Privacy')->badge(),
                TextColumn::make('context')->badge()->color('gray'),
                TextColumn::make('app_version')->label('App version')->placeholder('—'),
                TextColumn::make('accepted_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('terms_version')->label('Terms version')
                    ->options(fn () => self::versionOptions('terms_version')),
                SelectFilter::make('guidelines_version')->label('Guidelines version')
                    ->options(fn () => self::versionOptions('guidelines_version')),
            ]);
    }

    /** @return array<string, string> */
    private static function versionOptions(string $column): array
    {
        return TermsAcceptance::query()->distinct()->orderByDesc($column)->pluck($column, $column)->all();
    }
}
