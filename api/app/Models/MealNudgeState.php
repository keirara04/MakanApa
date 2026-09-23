<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'next_nudge_at', 'next_slot', 'unopened_streak', 'pause_count', 'paused_until', 'stopped_at'])]
class MealNudgeState extends Model
{
    protected function casts(): array
    {
        return [
            'next_nudge_at' => 'datetime',
            'paused_until' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaused(): bool
    {
        return $this->paused_until !== null && $this->paused_until->isFuture();
    }
}
