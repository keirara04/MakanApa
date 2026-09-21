<?php

namespace App\Filament\Resources\Restaurants;

use App\Models\Restaurant;
use App\Services\RestaurantMergeService;
use App\Services\RestaurantSubmissionModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

/**
 * Shared between RestaurantsTable's row actions and the Edit page's header actions — one
 * definition of "deactivate/reopen/merge a restaurant", not two.
 */
class RestaurantActions
{
    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('Deactivate')
            ->color('danger')
            ->visible(fn (Restaurant $record) => $record->is_active && $record->merged_into_restaurant_id === null)
            ->requiresConfirmation()
            ->action(fn (Restaurant $record) => app(RestaurantSubmissionModerationService::class)->remove($record, auth()->user()))
            ->successNotificationTitle('Restaurant deactivated');
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->color('success')
            ->visible(fn (Restaurant $record) => ! $record->is_active && $record->merged_into_restaurant_id === null)
            ->schema([
                Toggle::make('releaseToGoogle')
                    ->label('Release to Google (let Google decide open/closed going forward)')
                    ->default(false),
            ])
            ->requiresConfirmation()
            ->action(fn (Restaurant $record, array $data) => app(RestaurantSubmissionModerationService::class)
                ->reopen($record, (bool) ($data['releaseToGoogle'] ?? false), auth()->user()))
            ->successNotificationTitle('Restaurant reopened');
    }

    public static function merge(): Action
    {
        return Action::make('merge')
            ->label('Merge duplicate')
            ->color('warning')
            ->visible(fn (Restaurant $record) => $record->merged_into_restaurant_id === null)
            ->schema([
                Select::make('mergeIntoId')
                    ->label('This restaurant is a duplicate of…')
                    ->helperText('The other restaurant is kept; this one is marked merged and deactivated.')
                    ->options(fn (Restaurant $record) => Restaurant::where('id', '!=', $record->id)
                        ->whereNull('merged_into_restaurant_id')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalDescription('Saves, vibe votes, menu items, photos, submissions, and recommendations move to the kept restaurant. This one is never deleted — just marked as merged.')
            ->action(function (Restaurant $record, array $data) {
                $keep = Restaurant::findOrFail($data['mergeIntoId']);
                app(RestaurantMergeService::class)->merge($keep, $record, auth()->user());
            })
            ->successNotificationTitle('Restaurants merged');
    }
}
