<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materialized view of taste_events for one user (or anonymous installation). Never the source
 * of truth — `php artisan brain:rebuild-taste` reconstructs it from the event log.
 */
class TasteProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'memory' => 'array',
            'muted' => 'array',
            'corrections' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
