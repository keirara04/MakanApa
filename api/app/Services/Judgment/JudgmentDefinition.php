<?php

namespace App\Services\Judgment;

use App\Services\Judgment\Questions\Question;
use Illuminate\Database\Eloquent\Model;

/**
 * One versioned judgment purpose. ANY change to question wording, criteria, levels, options or
 * state shape must bump version() — old calibration data must never silently mix with new.
 *
 * State convention: buildState() returns named sections. `evidence` is what's being judged;
 * `context` is shown with an explicit anchoring guard (it may be wrong; evidence may contradict
 * it). Only user-submitted content and public listing data — never emails, phones or user ids.
 */
abstract class JudgmentDefinition
{
    abstract public function purpose(): string;

    abstract public function version(): int;

    /** @return list<Question> */
    abstract public function questions(): array;

    /** @return array<string, mixed> sanitized, deterministically trimmed state */
    abstract public function buildState(array $context): array;

    /**
     * Ground truth derived from an admin outcome, for calibration. Keys are question ids;
     * values are bool (binary/score, compared against probability / normalized score) or a
     * string (choice, compared against top choice). Omit questions with no derivable truth.
     */
    public function calibrationTargets(array $outcome): array
    {
        return [];
    }

    /** True if the same subject should be re-judged when its state changes (key includes the hash). */
    public function reevaluatesOnStateChange(): bool
    {
        return false;
    }

    public function idempotencyKey(?Model $subject, string $stateHash): ?string
    {
        if ($subject === null) {
            return null;
        }
        $key = "{$this->purpose()}:{$subject->getMorphClass()}:{$subject->getKey()}:v{$this->version()}";

        return $this->reevaluatesOnStateChange() ? "{$key}:{$stateHash}" : $key;
    }

    public function limit(string $name): int
    {
        return (int) config("judgment.limits.{$name}");
    }

    /** @return array<string, Question> */
    public function questionsById(): array
    {
        $byId = [];
        foreach ($this->questions() as $question) {
            $byId[$question->id] = $question;
        }

        return $byId;
    }
}
