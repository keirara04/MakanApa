<?php

namespace App\Models;

use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\HalalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'university_id', 'area_id', 'restaurant_id', 'submission_type', 'source_type', 'google_place_id',
    'name', 'address', 'food_category', 'price_level', 'phone', 'instagram_handle', 'tiktok_handle',
    'website_url', 'menu_items', 'latitude', 'longitude', 'location_source', 'notes', 'changed_fields',
    'status', 'reviewed_by', 'reviewed_at', 'review_note', 'halal_claim', 'halal_comment',
    'certification_authority', 'certificate_number', 'certificate_issued_at', 'certificate_expires_at',
    'halal_resolved_status', 'review_priority', 'contact_phone', 'triage', 'review_priority_breakdown',
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
            'halal_claim' => HalalStatus::class,
            'halal_resolved_status' => HalalStatus::class,
            'certification_authority' => CertificationAuthority::class,
            'certificate_issued_at' => 'date',
            'certificate_expires_at' => 'date',
            'triage' => 'array',
            'review_priority_breakdown' => 'array',
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

    public function photos(): HasMany
    {
        return $this->hasMany(RestaurantPhoto::class, 'restaurant_submission_id');
    }

    public function isHalalReport(): bool
    {
        return $this->submission_type === 'halal_report';
    }
}
