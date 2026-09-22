<?php

namespace App\Filament\Pages;

use App\Support\AdminTimezone;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Adds a display-timezone preference on top of Filament's stock profile page (name/email/
 * password) — see AdminTimezone and FilamentTimezone::set() in AdminPanelProvider for how this
 * gets applied to every timestamp the panel renders.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getDisplayTimezoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getDisplayTimezoneFormComponent(): Component
    {
        return Select::make('display_timezone')
            ->label('Timezone')
            ->helperText('Only changes how times are displayed to you in this panel — stored data stays in UTC.')
            ->options(AdminTimezone::OPTIONS)
            ->default(AdminTimezone::DEFAULT)
            ->required()
            ->native(false);
    }
}
