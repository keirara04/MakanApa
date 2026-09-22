<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'installation_id', 'token', 'platform', 'environment', 'last_seen_at', 'invalidated_at'])]
class DeviceToken extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
