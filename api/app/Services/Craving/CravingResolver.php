<?php

namespace App\Services\Craving;

use App\Support\FoodTaxonomy;
use Illuminate\Support\Facades\Cache;

/**
 * Turns a free-text craving into a CravingIntent, deterministic taxonomy first, AI only when
 * necessary — three confidence bands:
 *   >= 0.85  taxonomy result used as-is, AI never touched.
 *   0.60-0.84  AI may confirm/refine the local guess (passed as a hint, not discarded).
 *   < 0.60 / no local match  AI resolves fresh (or falls back to none if AI is unavailable).
 */
class CravingResolver
{
    private const STRONG_MATCH_THRESHOLD = 0.85;

    public function __construct(
        private readonly ?IntentParser $aiParser = null,
        private readonly ?DailyAiBudget $aiBudget = null,
    ) {}

    public function resolve(string $rawText): CravingIntent
    {
        $normalized = self::normalize($rawText);
        $local = FoodTaxonomy::resolve($normalized);
        $fallback = fn () => $local !== null ? CravingIntent::fromTaxonomy($rawText, $local) : CravingIntent::none($rawText);

        if ($local !== null && $local['confidence'] >= self::STRONG_MATCH_THRESHOLD) {
            return CravingIntent::fromTaxonomy($rawText, $local);
        }

        if ($this->aiParser === null) {
            return $fallback();
        }

        // Cache check happens before spending budget — a repeat of the same phrase must be
        // free, not another unit off the daily AI ceiling, or the cache stops doing its job.
        $cacheKey = "craving_intent:v1:{$normalized}";
        $cached = Cache::get($cacheKey);
        if ($cached instanceof CravingIntent) {
            return $cached;
        }

        if ($this->aiBudget === null || ! $this->aiBudget->tryConsume()) {
            return $fallback();
        }

        $parsed = $this->aiParser->parse($normalized, $local);

        if ($parsed === null) {
            // A transient failure (timeout, rate limit, error) must not poison this phrase for
            // 30 days — only genuine successes are cached long-term, so the very next request
            // gets an honest retry once the outage/budget recovers.
            return $fallback();
        }

        Cache::put($cacheKey, $parsed, now()->addDays(30));

        return $parsed;
    }

    public static function normalize(string $text): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
    }
}
