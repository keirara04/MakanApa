<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['room_member_id', 'preference_type', 'value', 'weight'])]
class RoomPreference extends Model
{
    public $timestamps = false;

    public function roomMember(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class);
    }
}
