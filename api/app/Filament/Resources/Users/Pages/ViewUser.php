<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** The support view — see UserInfolist. Same account actions as the edit page. */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            UserActions::suspend(),
            UserActions::reactivate(),
            UserActions::changeRole(),
            UserActions::revokeSessions(),
            UserActions::delete(),
            UserActions::restore(),
        ];
    }
}
