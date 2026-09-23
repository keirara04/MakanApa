<?php

namespace App\Services\Judgment;

use App\Models\AiJudgment;
use App\Services\Judgment\Answers\Answer;
use App\Services\Judgment\Answers\BinaryAnswer;
use App\Services\Judgment\Answers\ChoiceAnswer;
use App\Services\Judgment\Answers\ScoreAnswer;
use InvalidArgumentException;

final class JudgmentResult
{
    /** @param array<string, Answer> $answers */
    public function __construct(
        public readonly string $runId,
        public readonly ?int $judgmentId,
        public readonly string $purpose,
        public readonly int $definitionVersion,
        public readonly array $answers,
        public readonly bool $reused = false,
    ) {}

    public static function fromRecord(AiJudgment $record): self
    {
        return new self(
            $record->run_id,
            $record->id,
            $record->purpose,
            $record->definition_version,
            array_map(fn (array $a) => Answer::fromArray($a), $record->answers ?? []),
            reused: true,
        );
    }

    public function binary(string $id): BinaryAnswer
    {
        return $this->typed($id, BinaryAnswer::class);
    }

    public function choice(string $id): ChoiceAnswer
    {
        return $this->typed($id, ChoiceAnswer::class);
    }

    public function score(string $id): ScoreAnswer
    {
        return $this->typed($id, ScoreAnswer::class);
    }

    public function has(string $id): bool
    {
        return isset($this->answers[$id]);
    }

    /** Compact, JSON-safe summary for storing alongside the subject (e.g. submission.triage). */
    public function summary(): array
    {
        return [
            'judgmentId' => $this->judgmentId,
            'runId' => $this->runId,
            'purpose' => $this->purpose,
            'version' => $this->definitionVersion,
            'answers' => array_map(fn (Answer $a) => $a->toArray(), $this->answers),
        ];
    }

    /** @template T of Answer @param class-string<T> $class @return T */
    private function typed(string $id, string $class): Answer
    {
        $answer = $this->answers[$id] ?? null;
        if (! $answer instanceof $class) {
            throw new InvalidArgumentException("No {$class} answer for question [{$id}].");
        }

        return $answer;
    }
}
