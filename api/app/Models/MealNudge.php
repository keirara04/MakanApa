<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'local_date', 'slot', 'restaurant_id', 'copy_key', 'status', 'sent_at', 'opened_at', 'place_opened_at', 'acted_at', 'action'])]
class MealNudge extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Funnel events the app reports after a nudge is tapped. */
    public const EVENTS = ['opened', 'place_opened', 'makan_sini', 'quick_pick_started'];

    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'place_opened_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
