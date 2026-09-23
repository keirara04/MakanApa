<?php

namespace App\Services\Judgment;

use Illuminate\Database\Eloquent\Model;

/**
 * Ask typed questions about a state; get typed answers. Callers never see the provider,
 * transport, sampling or tokens. Returns null on failure, when the purpose is disabled, or
 * when its budget is exhausted — it never throws to the caller, so every consumer must have a
 * no-AI path.
 */
interface JudgmentEngine
{
    /**
     * @param  bool  $force  re-run even if an ok result already exists for this idempotency key
     */
    public function ask(JudgmentDefinition $definition, array $context, ?Model $subject = null, bool $force = false): ?JudgmentResult;
}
