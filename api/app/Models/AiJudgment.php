<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only Judgment System log row (one per run). Written only by the JudgmentEngine;
 * afterwards only recordOutcome() (calibration label) and judgments:prune (state purge) touch it.
 */
#[Fillable([
    'run_id', 'purpose', 'definition_version', 'provider', 'model', 'structured_mode', 'temperature',
    'samples', 'subject_type', 'subject_id', 'idempotency_key', 'state', 'state_hash', 'questions',
    'answers', 'attempts', 'input_tokens', 'output_tokens', 'latency_ms', 'status', 'failure_reason',
    'error', 'outcome', 'outcome_at',
])]
class AiJudgment extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'questions' => 'array',
            'answers' => 'array',
            'attempts' => 'array',
            'outcome' => 'array',
            'outcome_at' => 'datetime',
            'temperature' => 'float',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Labels the latest successful judgment of $purpose for $subject with what actually happened
     * (e.g. the admin's decision). Only ever sets outcome/outcome_at.
     */
    public static function recordOutcome(string $purpose, Model $subject, array $outcome): void
    {
        self::where('purpose', $purpose)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', 'ok')
            ->latest('id')
            ->limit(1)
            ->update(['outcome' => json_encode($outcome), 'outcome_at' => now()]);
    }
}
