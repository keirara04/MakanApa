<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Soft delete only — UserActions::delete()/restore(), never Filament's own DeleteAction
    // (that would hard-delete, bypassing AdminUserService's guards and audit log entirely).
    protected function getHeaderActions(): array
    {
        return [
            UserActions::suspend(),
            UserActions::reactivate(),
            UserActions::changeRole(),
            UserActions::revokeSessions(),
            UserActions::delete(),
            UserActions::restore(),
        ];
    }
}
