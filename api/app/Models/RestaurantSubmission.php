<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'university_id', 'restaurant_id', 'submission_type', 'source_type', 'google_place_id',
    'name', 'address', 'food_category', 'price_level', 'phone', 'instagram_handle', 'tiktok_handle',
    'website_url', 'menu_items', 'latitude', 'longitude', 'location_source', 'notes', 'changed_fields',
    'status', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class RestaurantSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'reviewed_at' => 'datetime',
            'changed_fields' => 'array',
            'menu_items' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    /** The existing canonical restaurant this submission targets (edit_place/closure) or resolved to (new_place, once approved/linked). */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
