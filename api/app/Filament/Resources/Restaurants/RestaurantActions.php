<?php

namespace App\Filament\Resources\Restaurants;

use App\Models\Restaurant;
use App\Services\RestaurantMergeService;
use App\Services\RestaurantSubmissionModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

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
                // Searched as you type (trigram-indexed name), not every restaurant preloaded into
                // the modal — that list is the whole Google Places sync.
                Select::make('mergeIntoId')
                    ->label('This restaurant is a duplicate of…')
                    ->helperText('The other restaurant is kept; this one is marked merged and deactivated.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Restaurant $record) => Restaurant::where('id', '!=', $record->id)
                        ->whereNull('merged_into_restaurant_id')
                        ->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => Restaurant::whereKey($value)->value('name'))
                    ->rules(['integer', 'exists:restaurants,id'])
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalDescription('Saves, vibe votes, menu items, photos, submissions, and recommendations move to the kept restaurant. This one is never deleted — just marked as merged.')
            ->action(function (Restaurant $record, array $data, Action $action) {
                $keep = Restaurant::findOrFail($data['mergeIntoId']);
                try {
                    app(RestaurantMergeService::class)->merge($keep, $record, auth()->user());
                } catch (\RuntimeException $e) {
                    // e.g. merged in another tab meanwhile: say why instead of an error page.
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->cancel();
                }
            })
            ->successNotificationTitle('Restaurants merged');
    }
}
