<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\RestaurantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRestaurant extends CreateRecord
{
    protected static string $resource = RestaurantResource::class;

    /** Fields another screen may prefill via the query string (e.g. Search misses → "Create place"). */
    private const PREFILLABLE = ['name', 'latitude', 'longitude'];

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $prefill = collect(request()->only(self::PREFILLABLE))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => mb_substr(trim($value), 0, 255))
            ->all();

        $this->form->fill($prefill);

        $this->callHook('afterFill');
    }
}
