<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One agreement to the Terms of Use + Community Guidelines (versions from config/legal.php),
 * written by POST me/terms-acceptance. Append-only: a new agreement is a new row.
 */
#[Fillable(['user_id', 'terms_version', 'guidelines_version', 'privacy_version', 'context', 'app_version', 'accepted_at'])]
class TermsAcceptance extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
