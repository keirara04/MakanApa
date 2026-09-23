<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['decision_id', 'type'])]
class DecisionInteraction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['detail_opened', 'directions_opened', 'reasons_expanded', 'what_if_opened', 'reopened'];
}
