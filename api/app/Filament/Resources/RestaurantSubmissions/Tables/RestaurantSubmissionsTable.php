<?php

namespace App\Filament\Resources\RestaurantSubmissions\Tables;

use App\Filament\Resources\RestaurantSubmissions\SubmissionActions;
use App\Models\RestaurantSubmission;
use App\Services\RestaurantSubmissionModerationService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class RestaurantSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'university']))
            ->columns([
                TextColumn::make('submission_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('halal_claim')
                    ->label('Halal claim')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('review_priority')
                    ->label('Priority')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'changes_requested' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('user.email')
                    ->label('Submitted by')
                    ->searchable(),
                TextColumn::make('university.short_name')
                    ->label('University'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'changes_requested' => 'Changes requested',
                    ])
                    ->default('pending'),
                SelectFilter::make('submission_type')
                    ->label('Type')
                    ->options([
                        'new_place' => 'New place',
                        'edit_place' => 'Edit place',
                        'closure' => 'Closure',
                        'reopen' => 'Reopen',
                        'halal_report' => 'Halal report',
                        'owner_claim' => 'Owner claim',
                    ]),
            ])
            ->recordActions([
                SubmissionActions::approve(),
                SubmissionActions::approveHalal(),
                SubmissionActions::reject(),
                SubmissionActions::requestChanges(),
                SubmissionActions::link(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // One shared reason (e.g. clearing spam); each submission still goes through
                    // the service, so each gets its own audit row and the submitter is notified.
                    BulkAction::make('bulkReject')
                        ->label('Reject selected')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->schema([
                            Textarea::make('reviewNote')->label('Reason')->required()->maxLength(500),
                        ])
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data) {
                            $pending = $records->where('status', 'pending');
                            $pending->each(fn (RestaurantSubmission $record) => app(RestaurantSubmissionModerationService::class)
                                ->reject($record, $data['reviewNote'], auth()->user()));

                            $skipped = $records->count() - $pending->count();
                            Notification::make()->success()
                                ->title($pending->count().' '.str('submission')->plural($pending->count()).' rejected')
                                ->body($skipped > 0 ? "{$skipped} skipped (no longer pending)." : null)
                                ->send();
                        }),
                ]),
            ]);
    }
}
