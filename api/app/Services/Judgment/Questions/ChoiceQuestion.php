<?php

namespace App\Services\Judgment\Questions;

use InvalidArgumentException;

/** One of a defined set. The answer is a probability per option. */
final class ChoiceQuestion extends Question
{
    /** @param array<string, string|null> $options option key => description */
    public function __construct(
        string $id,
        string|array $instructions,
        public readonly array $options,
        int $samples = 1,
    ) {
        if (count($options) < 2 || count($options) > 255) {
            throw new InvalidArgumentException("Choice {$id} needs 2-255 options.");
        }
        parent::__construct($id, $instructions, $samples);
    }

    public function type(): string
    {
        return 'choice';
    }

    public function outcomeKeys(): array
    {
        return array_map('strval', array_keys($this->options));
    }

    public function describe(): array
    {
        return [
            'id' => $this->id,
            'type' => 'choice',
            'instructions' => $this->instructions,
            'options' => $this->options,
            'answer_with' => '`probabilities`: a probability for every option key; they should sum to 1.',
        ];
    }
}
