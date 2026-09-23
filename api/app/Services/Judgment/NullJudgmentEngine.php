<?php

namespace App\Services\Judgment;

use Illuminate\Database\Eloquent\Model;

/** No OpenRouter key configured: every judgment is simply unavailable. */
final class NullJudgmentEngine implements JudgmentEngine
{
    public function ask(JudgmentDefinition $definition, array $context, ?Model $subject = null, bool $force = false): ?JudgmentResult
    {
        return null;
    }
}
