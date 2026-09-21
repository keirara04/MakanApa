<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'latitude', 'longitude', 'radius_km', 'synced_at', 'types'])]
class PlaceSyncArea extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_km' => 'decimal:4',
            'synced_at' => 'datetime',
            'types' => 'array',
        ];
    }
}
