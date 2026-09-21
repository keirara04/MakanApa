<?php

namespace App\Filament\Resources\RestaurantSubmissions;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Services\RestaurantSubmissionModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

/**
 * Shared between the submissions table's row actions and the View page's header actions so
 * "approve from the list" and "approve from the detail page" are the exact same action —
 * both ultimately call RestaurantSubmissionModerationService, never duplicate the logic.
 */
class SubmissionActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                Toggle::make('releaseToGoogle')
                    ->label('Release to Google (only applies to reopen requests)')
                    ->default(false),
            ])
            ->requiresConfirmation()
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->approve(
                    $record,
                    auth()->user(),
                    (bool) ($data['releaseToGoogle'] ?? false)
                );
            })
            ->successNotificationTitle('Submission approved');
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                Textarea::make('reviewNote')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->requiresConfirmation()
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->reject($record, $data['reviewNote'], auth()->user());
            })
            ->successNotificationTitle('Submission rejected');
    }

    public static function requestChanges(): Action
    {
        return Action::make('requestChanges')
            ->label('Request changes')
            ->color('warning')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                Textarea::make('reviewNote')
                    ->label('What needs to change')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->requestChanges($record, $data['reviewNote'], auth()->user());
            })
            ->successNotificationTitle('Changes requested');
    }

    public static function link(): Action
    {
        return Action::make('link')
            ->label('Link to existing restaurant')
            ->color('gray')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                Select::make('restaurantId')
                    ->label('Restaurant')
                    ->options(fn () => Restaurant::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->link($record, (int) $data['restaurantId'], auth()->user());
            })
            ->successNotificationTitle('Submission linked to existing restaurant');
    }
}
