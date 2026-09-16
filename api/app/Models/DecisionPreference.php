<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['decision_id', 'preference_type', 'value'])]
class DecisionPreference extends Model
{
    public $timestamps = false;

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
