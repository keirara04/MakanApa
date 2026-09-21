<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // No DeleteAction — accounts are suspended, never hard-deleted, from the admin panel.
    protected function getHeaderActions(): array
    {
        return [
            UserActions::suspend(),
            UserActions::reactivate(),
            UserActions::changeRole(),
            UserActions::revokeSessions(),
        ];
    }
}
