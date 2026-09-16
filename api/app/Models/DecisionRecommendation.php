<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['decision_id', 'restaurant_id', 'rank', 'score', 'shown_at', 'accepted_at', 'rejected_at'])]
class DecisionRecommendation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'shown_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
