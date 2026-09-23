<?php

namespace App\Services\Judgment;

use App\Models\AiJudgment;
use App\Services\Craving\DailyAiBudget;
use App\Services\Judgment\Answers\BinaryAnswer;
use App\Services\Judgment\Answers\ChoiceAnswer;
use App\Services\Judgment\Answers\ScoreAnswer;
use App\Services\Judgment\Questions\BinaryQuestion;
use App\Services\Judgment\Questions\ChoiceQuestion;
use App\Services\Judgment\Questions\Question;
use App\Services\Judgment\Questions\ScoreQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * Judgment engine over OpenRouter chat completions (default model: Claude Haiku 4.5).
 *
 * One provider request per sample; each request carries every question still needing samples,
 * answered as a single structured object (strict json_schema, or a forced tool call for models
 * without schema support). Probabilities are normalized and aggregated in code; confidence is
 * derived from distributions, never self-reported. Every run — success or failure — is one
 * append-only ai_judgments row sharing a run_id with its per-request attempts.
 */
final class OpenRouterJudgmentEngine implements JudgmentEngine
{
    private const TOOL_NAME = 'submit_judgment';

    public function __construct(private readonly string $apiKey) {}

    public function ask(JudgmentDefinition $definition, array $context, ?Model $subject = null, bool $force = false): ?JudgmentResult
    {
        $runId = 'judg_'.Str::lower((string) Str::ulid());
        $purpose = $definition->purpose();
        $purposeConfig = config("judgment.purposes.{$purpose}", []);
        $questions = $definition->questionsById();
        $samples = max(array_map(fn (Question $q) => $q->samples, $questions));
        $temperature = $samples > 1 ? 1.0 : 0.0;
        $model = $purposeConfig['model'] ?? null ?: config('judgment.model');
        $mode = config('judgment.mode') === 'tools' ? 'tools' : 'json_schema';

        $state = $definition->buildState($context);
        $stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stateHash = hash('sha256', $stateJson);
        $idempotencyKey = $definition->idempotencyKey($subject, $stateHash);

        $base = [
            'run_id' => $runId,
            'purpose' => $purpose,
            'definition_version' => $definition->version(),
            'provider' => 'openrouter',
            'model' => $model,
            'structured_mode' => $mode,
            'temperature' => $temperature,
            'samples' => $samples,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'idempotency_key' => $idempotencyKey,
            'state' => $state,
            'state_hash' => $stateHash,
            'questions' => array_values(array_map(fn (Question $q) => $q->describe() + ['samples' => $q->samples], $questions)),
        ];

        if (! ($purposeConfig['enabled'] ?? false)) {
            return $this->fail($base, 'disabled', FailureReason::Disabled);
        }

        if (mb_strlen($stateJson) > $definition->limit('max_state_chars')) {
            return $this->fail($base, 'failed', FailureReason::StateTooLarge, 'State '.mb_strlen($stateJson).' chars after trimming.');
        }

        if ($idempotencyKey !== null && ! $force) {
            $existing = AiJudgment::where('idempotency_key', $idempotencyKey)->where('status', 'ok')->first();
            if ($existing) {
                return JudgmentResult::fromRecord($existing);
            }
        }

        $budget = isset($purposeConfig['daily_limit']) ? new DailyAiBudget((int) $purposeConfig['daily_limit'], "judgment:{$purpose}") : null;
        $collected = array_fill_keys(array_keys($questions), []);
        $attempts = [];
        $tokensIn = $tokensOut = 0;
        $started = microtime(true);
        $lastFailure = null;
        $lastError = null;

        for ($sample = 0; $sample < $samples; $sample++) {
            // Only questions still owed samples ride along on this request.
            $pending = array_filter($questions, fn (Question $q) => $q->samples > $sample);

            if ($budget && ! $budget->tryConsume()) {
                $lastFailure = FailureReason::BudgetExhausted;
                break;
            }

            [$parsed, $attemptLog, $failure, $error, $usage] = $this->requestWithRetry($runId, $purpose, $model, $mode, $temperature, $state, $pending, $sample, $purposeConfig);
            $attempts = array_merge($attempts, $attemptLog);
            $tokensIn += $usage[0];
            $tokensOut += $usage[1];

            if ($parsed === null) {
                $lastFailure = $failure;
                $lastError = $error;

                continue;
            }

            foreach ($parsed as $id => $value) {
                $collected[$id][] = $value;
            }
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $metrics = ['attempts' => $attempts, 'input_tokens' => $tokensIn, 'output_tokens' => $tokensOut, 'latency_ms' => $latencyMs];

        // Every question needs at least one good sample, or the run as a whole failed.
        foreach ($collected as $values) {
            if ($values === []) {
                $status = $lastFailure === FailureReason::BudgetExhausted ? 'budget_exhausted' : 'failed';

                return $this->fail($base + $metrics, $status, $lastFailure ?? FailureReason::ProviderError, $lastError);
            }
        }

        $answers = [];
        foreach ($questions as $id => $question) {
            $answers[$id] = match (true) {
                $question instanceof BinaryQuestion => BinaryAnswer::fromSamples($collected[$id]),
                $question instanceof ChoiceQuestion => ChoiceAnswer::fromSamples($collected[$id]),
                $question instanceof ScoreQuestion => ScoreAnswer::fromSamples($collected[$id], $question->maxLevel()),
            };
        }

        try {
            $record = AiJudgment::create($base + $metrics + [
                'answers' => array_map(fn ($a) => $a->toArray(), $answers),
                'status' => 'ok',
                // A partial run (some samples failed) is still ok, but the reason is kept.
                'failure_reason' => $lastFailure?->value,
                'error' => $lastError,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent run for the same subject+version won the race — reuse its answers.
            $existing = AiJudgment::where('idempotency_key', $idempotencyKey)->where('status', 'ok')->first();

            return $existing ? JudgmentResult::fromRecord($existing) : null;
        }

        return new JudgmentResult($runId, $record->id, $purpose, $definition->version(), $answers);
    }

    /**
     * One sample: the request plus at most one retry on a retryable failure.
     *
     * @return array{0: ?array, 1: list<array>, 2: ?FailureReason, 3: ?string, 4: array{int, int}}
     */
    private function requestWithRetry(string $runId, string $purpose, string $model, string $mode, float $temperature, array $state, array $questions, int $sample, array $purposeConfig): array
    {
        $log = [];
        $tokens = [0, 0];
        $failure = null;
        $error = null;

        for ($retry = 0; $retry <= 1; $retry++) {
            $started = microtime(true);
            $httpStatus = null;
            $parsed = null;
            $failure = null;
            $error = null;

            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout((int) ($purposeConfig['timeout'] ?? config('judgment.timeout')))
                    ->connectTimeout((int) config('judgment.connect_timeout'))
                    ->withHeaders(['X-Title' => 'MakanApa'])
                    ->post(config('judgment.endpoint'), $this->payload($model, $mode, $temperature, $state, $questions));
                $httpStatus = $response->status();
                $tokens[0] += (int) $response->json('usage.prompt_tokens', 0);
                $tokens[1] += (int) $response->json('usage.completion_tokens', 0);

                if ($response->successful()) {
                    [$parsed, $failure, $error] = $this->parse($response, $mode, $questions);
                } else {
                    $failure = $httpStatus === 429 ? FailureReason::RateLimited : FailureReason::ProviderError;
                    $error = Str::limit((string) $response->body(), 300);
                }
            } catch (ConnectionException $e) {
                $failure = FailureReason::Timeout;
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $failure = FailureReason::ProviderError;
                $error = $e->getMessage();
            }

            $log[] = [
                'sample' => $sample,
                'retry' => $retry,
                'httpStatus' => $httpStatus,
                'latencyMs' => (int) round((microtime(true) - $started) * 1000),
                'failureReason' => $failure?->value,
            ];

            if ($failure === null) {
                return [$parsed, $log, null, null, $tokens];
            }

            Log::warning('Judgment request failed', [
                'run_id' => $runId, 'purpose' => $purpose, 'sample' => $sample, 'retry' => $retry,
                'reason' => $failure->value, 'status' => $httpStatus,
            ]);

            if (! $failure->isRetryable() || $retry === 1) {
                break;
            }
            Sleep::for((int) config('judgment.retry_backoff_ms'))->milliseconds();
        }

        return [null, $log, $failure, $error, $tokens];
    }

    private function payload(string $model, string $mode, float $temperature, array $state, array $questions): array
    {
        $schema = $this->schema($questions);
        $payload = [
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => (int) config('judgment.max_output_tokens'),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($state, $questions)],
            ],
        ];

        if ($mode === 'tools') {
            $payload['tools'] = [[
                'type' => 'function',
                'function' => ['name' => self::TOOL_NAME, 'description' => 'Submit the judgment answers.', 'parameters' => $schema],
            ]];
            $payload['tool_choice'] = ['type' => 'function', 'function' => ['name' => self::TOOL_NAME]];
        } else {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'judgment', 'strict' => true, 'schema' => $schema],
            ];
        }

        return $payload;
    }

    /** @param array<string, Question> $questions */
    public function schema(array $questions): array
    {
        $properties = [];
        foreach ($questions as $id => $question) {
            $properties[$id] = $question instanceof BinaryQuestion
                ? $this->objectSchema(['p_yes' => ['type' => 'number']])
                : $this->objectSchema(['probabilities' => $this->objectSchema(
                    array_fill_keys($question->outcomeKeys(), ['type' => 'number'])
                )]);
        }

        return $this->objectSchema($properties);
    }

    private function objectSchema(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_map('strval', array_keys($properties)),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, Question>  $questions
     * @return array{0: ?array<string, mixed>, 1: ?FailureReason, 2: ?string}
     */
    private function parse(Response $response, string $mode, array $questions): array
    {
        $raw = $mode === 'tools'
            ? $response->json('choices.0.message.tool_calls.0.function.arguments')
            : $response->json('choices.0.message.content');

        $data = is_array($raw) ? $raw : (is_string($raw) ? json_decode($this->stripFences($raw), true) : null);
        if (! is_array($data)) {
            return [null, FailureReason::InvalidJson, 'Unparseable model output.'];
        }

        $values = [];
        foreach ($questions as $id => $question) {
            $answer = $data[$id] ?? null;
            if ($question instanceof BinaryQuestion) {
                if (! is_array($answer) || ! is_numeric($answer['p_yes'] ?? null)) {
                    return [null, FailureReason::SchemaValidation, "Missing p_yes for {$id}."];
                }
                $values[$id] = max(0.0, min(1.0, (float) $answer['p_yes']));

                continue;
            }

            $probabilities = is_array($answer['probabilities'] ?? null) ? $answer['probabilities'] : null;
            $keys = $question->outcomeKeys();
            if ($probabilities === null || array_diff($keys, array_map('strval', array_keys($probabilities))) !== []) {
                return [null, FailureReason::SchemaValidation, "Incomplete distribution for {$id}."];
            }
            $normalized = Statistics::normalize(array_intersect_key($probabilities, array_flip($keys)));
            if ($normalized === null) {
                return [null, FailureReason::SchemaValidation, "Empty distribution for {$id}."];
            }
            // Keep the question's own key order (stable logs, stable argmax ties). Built by hand —
            // array_merge would renumber Score's numeric level keys.
            $ordered = [];
            foreach ($keys as $key) {
                $ordered[$key] = $normalized[$key] ?? 0.0;
            }
            $values[$id] = $ordered;
        }

        return [$values, null, null];
    }

    private function stripFences(string $text): string
    {
        return trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)) ?? $text);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a careful judgment component inside the MakanApa food app (Malaysia). You never
            write prose. For each question you return probabilities in the exact structure requested.

            Rules:
            - Judge only what the state supports. If the evidence is thin or ambiguous, spread
              probability accordingly instead of guessing confidently.
            - Probabilities are your honest belief. 0.5 means "genuinely can't tell", not "medium".
            - Sections labelled EVIDENCE TO JUDGE are what you are judging.
            - Sections labelled CONTEXT ONLY describe the current listing or automatic checks. They
              may be wrong or out of date. Do not assume they are correct; new evidence may
              legitimately contradict them. Never treat existing status as evidence for itself.
            - Text may be in English, Malay, Manglish or Chinese dialect terms.
            PROMPT;
    }

    private function userPrompt(array $state, array $questions): string
    {
        $labels = [
            'evidence' => 'EVIDENCE TO JUDGE',
            'context' => 'CONTEXT ONLY — current listing / automatic checks. Do not assume this is correct; new evidence may legitimately contradict it.',
        ];

        $parts = [];
        foreach ($state as $section => $content) {
            $label = $labels[$section] ?? strtoupper((string) $section);
            $parts[] = "## {$label}\n".json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $parts[] = "## QUESTIONS\n".json_encode(
            array_values(array_map(fn (Question $q) => $q->describe(), $questions)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $parts[] = 'Answer every question by its id, in the required structure.';

        return implode("\n\n", $parts);
    }

    private function fail(array $row, string $status, FailureReason $reason, ?string $error = null): null
    {
        AiJudgment::create($row + ['status' => $status, 'failure_reason' => $reason->value, 'error' => $error]);

        return null;
    }
}
