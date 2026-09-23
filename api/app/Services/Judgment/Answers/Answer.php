<?php

namespace App\Services\Judgment\Answers;

/**
 * Every answer carries its sampling statistics. Averaged repeated samples measure the
 * STABILITY of the model's judgment — they are not a calibrated probability. Calibration comes
 * from logged outcomes (judgments:calibration).
 */
abstract class Answer
{
    abstract public function toArray(): array;

    public static function fromArray(array $data): self
    {
        return match ($data['type']) {
            'binary' => BinaryAnswer::fromArray($data),
            'choice' => ChoiceAnswer::fromArray($data),
            'score' => ScoreAnswer::fromArray($data),
        };
    }
}
