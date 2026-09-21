<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'restaurant_id', 'restaurant_submission_id', 'disk', 'path', 'photo_type',
    'width', 'height', 'size_bytes', 'uploaded_by', 'is_active',
])]
class RestaurantPhoto extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Only meaningful once `disk` is the public disk — pending photos have no public URL. */
    public function publicUrl(): ?string
    {
        if ($this->disk !== config('restaurant_photos.public_disk')) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
