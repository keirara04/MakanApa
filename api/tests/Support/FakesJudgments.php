<?php

namespace Tests\Support;

use App\Services\Judgment\JudgmentEngine;
use App\Support\FoodTaxonomy;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

/** Builds OpenRouter-shaped judgment responses; never touches the real API. */
trait FakesJudgments
{
    protected function enableJudgments(): void
    {
        config(['services.openrouter.api_key' => 'fake-openrouter-key']);
        $this->app->forgetInstance(JudgmentEngine::class);
    }

    /** @param array<string, mixed> $answers question id => {p_yes} | {probabilities} */
    protected function judgmentResponse(array $answers, int $promptTokens = 120, int $completionTokens = 30): PromiseInterface
    {
        return Http::response([
            'choices' => [['message' => ['content' => json_encode($answers)]]],
            'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens],
        ]);
    }

    /** A craving Choice answer with $top probability on $concept, rest on `none`. */
    protected function cravingAnswer(string $concept, float $top = 0.9): array
    {
        $probabilities = array_fill_keys([...array_keys(FoodTaxonomy::CONCEPTS), 'none'], 0.0);
        $probabilities[$concept] = $top;
        if ($concept !== 'none') {
            $probabilities['none'] = 1 - $top;
        }

        return ['concept' => ['probabilities' => $probabilities]];
    }
}
