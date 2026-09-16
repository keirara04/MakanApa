<?php

namespace App\Services\Craving;

use App\Support\FoodTaxonomy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns free-text craving into a known FoodTaxonomy concept via OpenRouter — never a raw
 * Google query. The model only ever picks from the taxonomy's own vocabulary; it does not
 * author search text (see FoodTaxonomy::searchTermsFor()). A broken/slow/wrong-shaped response
 * degrades to null (the caller falls back to taxonomy/none) — this must never surface as a
 * request failure.
 */
class OpenRouterIntentParser implements IntentParser
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function parse(string $normalizedText, ?array $localHint): ?CravingIntent
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(8)
                ->connectTimeout(2)
                ->post(self::ENDPOINT, [
                    'model' => $this->model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $normalizedText],
                    ],
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('OpenRouter craving parse failed', ['error' => $e->getMessage(), 'query' => $normalizedText]);

            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            return null;
        }

        $parsed = json_decode($content, true);
        if (! is_array($parsed)) {
            return null;
        }

        return $this->toIntent($normalizedText, $parsed, $localHint);
    }

    /**
     * Only ever resolves to a concept already present in FoodTaxonomy — an invented category
     * is dropped, falling back to $localHint (a decent local guess) rather than either
     * trusting the model blindly or discarding a usable hint.
     */
    private function toIntent(string $raw, array $parsed, ?array $localHint): ?CravingIntent
    {
        $concept = $parsed['subcategory'] ?? $parsed['category'] ?? null;
        $confidence = is_numeric($parsed['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $parsed['confidence']))
            : 0.75;

        if (is_string($concept) && FoodTaxonomy::exists($concept)) {
            return CravingIntent::fromAi($raw, $concept, $confidence);
        }

        if ($localHint !== null) {
            return CravingIntent::fromTaxonomy($raw, $localHint);
        }

        return null;
    }

    private function systemPrompt(): string
    {
        $concepts = implode(', ', array_keys(FoodTaxonomy::CONCEPTS));

        return <<<PROMPT
            You classify a short food craving phrase, possibly in Malay/Manglish, into a known
            concept. Respond with a single JSON object only, no prose, with this exact shape:
            {"category": string|null, "subcategory": string|null, "attributes": string[], "budget": string|null, "confidence": number}

            "category" and "subcategory" MUST be one of these exact values, or null if none fit:
            {$concepts}

            Never invent a value outside that list. "confidence" is 0-1, how sure you are.
            PROMPT;
    }
}
