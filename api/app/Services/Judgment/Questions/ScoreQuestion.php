<?php

namespace App\Services\Judgment\Questions;

use InvalidArgumentException;

/** Degree on ordered, self-contained levels (index 0..n-1). */
final class ScoreQuestion extends Question
{
    /** @param list<string> $levels ordered level descriptions, lowest first */
    public function __construct(
        string $id,
        string|array $instructions,
        public readonly array $levels,
        int $samples = 1,
    ) {
        if (count($levels) < 2 || count($levels) > 10) {
            throw new InvalidArgumentException("Score {$id} needs 2-10 levels.");
        }
        parent::__construct($id, $instructions, $samples);
    }

    public function type(): string
    {
        return 'score';
    }

    public function outcomeKeys(): array
    {
        return array_map('strval', array_keys(array_values($this->levels)));
    }

    public function describe(): array
    {
        return [
            'id' => $this->id,
            'type' => 'score',
            'instructions' => $this->instructions,
            'levels' => array_combine($this->outcomeKeys(), array_values($this->levels)),
            'answer_with' => '`probabilities`: a probability for every level key; they should sum to 1.',
        ];
    }

    public function maxLevel(): int
    {
        return count($this->levels) - 1;
    }
}
