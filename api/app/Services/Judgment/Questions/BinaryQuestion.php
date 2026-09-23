<?php

namespace App\Services\Judgment\Questions;

/** Yes/no: the answer is the probability that the answer is yes. */
final class BinaryQuestion extends Question
{
    public function __construct(
        string $id,
        string|array $instructions,
        public readonly ?string $yesMeans = null,
        public readonly ?string $noMeans = null,
        int $samples = 1,
    ) {
        parent::__construct($id, $instructions, $samples);
    }

    public function type(): string
    {
        return 'binary';
    }

    public function outcomeKeys(): array
    {
        return ['p_yes'];
    }

    public function describe(): array
    {
        return array_filter([
            'id' => $this->id,
            'type' => 'yes_no',
            'instructions' => $this->instructions,
            'yes_means' => $this->yesMeans,
            'no_means' => $this->noMeans,
            'answer_with' => '`p_yes`: your probability (0-1) that the answer is yes.',
        ], fn ($v) => $v !== null);
    }
}
