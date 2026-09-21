<?php

namespace App\Filament\Resources\AccountDeletions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountDeletionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('user_id')->label('Former user ID'),
                TextColumn::make('deleted_at')->dateTime()->sortable(),
            ]);
    }
}
