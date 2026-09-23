<?php

namespace App\Services\Judgment\Questions;

/**
 * A typed question. The id is for code only; `instructions` must carry the question's full
 * meaning. Any wording/criteria change belongs in a new JudgmentDefinition version.
 */
abstract class Question
{
    public const MAX_SAMPLES = 5;

    public readonly int $samples;

    public function __construct(
        public readonly string $id,
        public readonly string|array $instructions,
        int $samples = 1,
    ) {
        $this->samples = max(1, min(self::MAX_SAMPLES, $samples));
    }

    abstract public function type(): string;

    /** Keys the model must return a probability for (normalized to 1 in code). */
    abstract public function outcomeKeys(): array;

    /** How the question is shown to the model. */
    abstract public function describe(): array;
}
