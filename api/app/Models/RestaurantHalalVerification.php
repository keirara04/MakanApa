<?php

namespace App\Models;

use App\Support\Halal\HalalDecisionMethod;
use App\Support\Halal\HalalEvidenceSource;
use App\Support\Halal\HalalStatus;
use App\Support\Halal\HalalVerificationState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One halal decision in a restaurant's append-only ledger. Created only by
 * HalalVerificationService — never insert/update rows directly, or the supersession chain and
 * the restaurants.halal_* snapshot drift apart.
 */
#[Fillable([
    'restaurant_id', 'submission_id', 'certificate_id', 'moderator_id', 'status', 'evidence_source',
    'decision_method', 'state', 'evidence_summary', 'override_reason', 'heuristic_matches',
    'effective_from', 'effective_until', 'superseded_by_id',
])]
class RestaurantHalalVerification extends Model
{
    protected function casts(): array
    {
        return [
            'status' => HalalStatus::class,
            'evidence_source' => HalalEvidenceSource::class,
            'decision_method' => HalalDecisionMethod::class,
            'state' => HalalVerificationState::class,
            'heuristic_matches' => 'array',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RestaurantSubmission::class, 'submission_id');
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(RestaurantHalalCertificate::class, 'certificate_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
