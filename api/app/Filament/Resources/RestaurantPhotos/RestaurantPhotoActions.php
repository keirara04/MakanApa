<?php

namespace App\Filament\Resources\RestaurantPhotos;

use App\Models\RestaurantPhoto;
use App\Services\AdminAuditLogger;
use Filament\Actions\Action;

/** Shared between the global RestaurantPhotoResource table and RestaurantResource's nested PhotosRelationManager. */
class RestaurantPhotoActions
{
    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('Hide')
            ->color('danger')
            ->visible(fn (RestaurantPhoto $record) => $record->is_active)
            ->requiresConfirmation()
            ->action(fn (RestaurantPhoto $record) => self::setVisibility($record, false));
    }

    public static function restore(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->color('success')
            ->visible(fn (RestaurantPhoto $record) => ! $record->is_active)
            ->action(fn (RestaurantPhoto $record) => self::setVisibility($record, true));
    }

    private static function setVisibility(RestaurantPhoto $photo, bool $visible): void
    {
        $photo->update(['is_active' => $visible]);

        app(AdminAuditLogger::class)->log(
            auth()->user(),
            $visible ? 'photo.restore' : 'photo.hide',
            $photo,
        );
    }
}
