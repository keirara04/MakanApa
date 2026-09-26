<?php

namespace App\Filament\Resources\AdminAuditLogs\Tables;

use App\Models\AdminAuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class AdminAuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('admin:id,email'))
            // Newest first by primary key — same order as created_at, without needing its index.
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('admin.email')->label('Admin'),
                TextColumn::make('action')->badge(),
                TextColumn::make('subject_type')->label('Subject')->formatStateUsing(fn (string $state) => class_basename($state)),
                TextColumn::make('subject_id'),
                TextColumn::make('reason')->placeholder('—')->limit(60),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => Cache::remember('admin-filter:audit-log-actions', now()->addMinutes(10), fn () => AdminAuditLog::query()->distinct()->pluck('action', 'action')->all())),
            ]);
    }
}
