<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'short_name', 'country', 'latitude', 'longitude', 'active'])]
class University extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
