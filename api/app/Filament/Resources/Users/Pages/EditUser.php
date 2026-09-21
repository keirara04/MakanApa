<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Services\AdminAuditLogger;
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

    /**
     * The one field on this form that isn't action-only (see UserForm's doc comment) — audited
     * here instead, and existing sessions are revoked since whoever was signed in with the old
     * password shouldn't stay signed in silently once an admin has changed it.
     */
    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('password')) {
            return;
        }

        $this->record->tokens()->delete();

        app(AdminAuditLogger::class)->log(auth()->user(), 'user.change_password', $this->record);
    }
}
