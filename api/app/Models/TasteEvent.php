<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only. Written only through App\Services\Brain\TasteEventRecorder. */
class TasteEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
