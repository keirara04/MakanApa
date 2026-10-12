<?php

namespace App\Models;

use App\Support\Region;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'name', 'description', 'price', 'currency', 'category', 'is_available', 'sort_order', 'source_submission_id'])]
class RestaurantMenuItem extends Model
{
    /** Prices are in the money of where the restaurant is, whoever adds them (moderation or admin). */
    protected static function booted(): void
    {
        static::creating(function (RestaurantMenuItem $item): void {
            if ($item->currency === null && ($restaurant = $item->restaurant) !== null) {
                $item->currency = Region::at((float) $restaurant->latitude, (float) $restaurant->longitude)->currency();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
