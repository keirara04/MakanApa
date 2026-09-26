<?php

namespace App\Filament\Resources\CommunityRequests\Tables;

use App\Http\Controllers\Api\Admin\CommunityRequestController;
use App\Models\CommunityRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class CommunityRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('name'),
                TextColumn::make('user.email')->label('Requested by'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'])
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->color('success')
                    ->visible(fn (CommunityRequest $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn (CommunityRequest $record) => app(CommunityRequestController::class)->resolve($record)),
                Action::make('dismiss')
                    ->color('gray')
                    ->visible(fn (CommunityRequest $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn (CommunityRequest $record) => app(CommunityRequestController::class)->dismiss($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Same path as the row action, so each request gets its own audit row.
                    BulkAction::make('bulkDismiss')
                        ->label('Dismiss selected')
                        ->color('gray')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $pending = $records->where('status', 'pending');
                            $pending->each(fn (CommunityRequest $record) => app(CommunityRequestController::class)->dismiss($record));

                            Notification::make()->success()
                                ->title($pending->count().' '.str('request')->plural($pending->count()).' dismissed')
                                ->send();
                        }),
                ]),
            ]);
    }
}
