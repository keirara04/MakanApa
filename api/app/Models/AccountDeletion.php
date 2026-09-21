<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'email', 'deleted_at'])]
class AccountDeletion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
