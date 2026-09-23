<?php

namespace App\Services\Craving;

use App\Services\Judgment\Definitions\CravingChoiceV1;
use App\Services\Judgment\JudgmentEngine;

/**
 * Craving -> FoodTaxonomy concept via the Judgment System (a Choice over the taxonomy plus
 * `none`). Confidence is derived from the returned distribution, not self-reported. `none` or a
 * low-confidence pick falls back to the local keyword guess (or null -> caller's fallback) —
 * this must never surface as a request failure.
 */
class JudgmentIntentParser implements IntentParser
{
    public const MIN_CONFIDENCE = 0.55;

    public function __construct(private readonly JudgmentEngine $engine) {}

    public function parse(string $normalizedText, ?array $localHint): ?CravingIntent
    {
        $result = $this->engine->ask(new CravingChoiceV1, ['text' => $normalizedText, 'localHint' => $localHint]);
        if ($result === null) {
            return null;
        }

        $answer = $result->choice('concept');
        $top = $answer->probabilityOf($answer->choice);

        if ($answer->choice !== CravingChoiceV1::NONE && $top >= self::MIN_CONFIDENCE) {
            $intent = CravingIntent::fromAi($normalizedText, $answer->choice, $top);
            if ($intent !== null) {
                return $intent;
            }
        }

        return $localHint !== null ? CravingIntent::fromTaxonomy($normalizedText, $localHint) : null;
    }
}
