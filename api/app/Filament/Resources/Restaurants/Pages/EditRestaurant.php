<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\HalalActions;
use App\Filament\Resources\Restaurants\RestaurantActions;
use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Models\RestaurantFieldOverride;
use App\Services\AdminAuditLogger;
use App\Support\RestaurantField;
use Filament\Resources\Pages\EditRecord;

class EditRestaurant extends EditRecord
{
    protected static string $resource = RestaurantResource::class;

    // No DeleteAction — restaurants are never hard-deleted, only deactivated or merged.
    protected function getHeaderActions(): array
    {
        return [
            RestaurantActions::deactivate(),
            RestaurantActions::reopen(),
            RestaurantActions::merge(),
            HalalActions::override(),
        ];
    }

    /**
     * An admin edit to a Google place would be overwritten by the next sync unless the field is
     * pinned: same override row a moderated edit writes (authority admin), plus an audit entry.
     */
    protected function afterSave(): void
    {
        $changed = array_values(array_intersect(array_keys($this->record->getChanges()), RestaurantField::OVERRIDABLE));
        if ($changed === []) {
            return;
        }

        if ($this->record->provider === 'google') {
            foreach ($changed as $field) {
                RestaurantFieldOverride::updateOrCreate(
                    ['restaurant_id' => $this->record->id, 'field' => $field],
                    [
                        'restaurant_submission_id' => null,
                        'value' => $this->record->getAttribute($field),
                        'authority' => 'admin',
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                    ]
                );
            }
        }

        app(AdminAuditLogger::class)->log(auth()->user(), 'restaurant.edit', $this->record, metadata: ['fields' => $changed]);
    }
}
