<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'restaurant_submission_id', 'field', 'value', 'authority', 'verified_by', 'verified_at'])]
class RestaurantFieldOverride extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'verified_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RestaurantSubmission::class, 'restaurant_submission_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
