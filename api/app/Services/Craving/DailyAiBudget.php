<?php

namespace App\Services\Craving;

use Illuminate\Support\Facades\Cache;

/**
 * This app's own configurable ceiling on OpenRouter calls per day — not a promise about
 * OpenRouter's free-tier shape, which can change. Kept deliberately separate from
 * CravingResolver so the "is there budget" and "spend budget" steps can't be split into two
 * calls that race under concurrent requests.
 */
class DailyAiBudget
{
    public function __construct(private readonly int $dailyLimit) {}

    /**
     * Atomically increments today's counter and reports whether this call is still within
     * budget — a single operation, not check-then-record, so two concurrent requests can't
     * both observe "39 used" and both proceed.
     */
    public function tryConsume(): bool
    {
        $key = $this->cacheKey();

        // add() only seeds the counter (and its TTL) the first time today; increment() is the
        // single atomic spend-and-read operation every call relies on.
        Cache::add($key, 0, now()->endOfDay()->addHour());
        $count = Cache::increment($key);

        return $count <= $this->dailyLimit;
    }

    private function cacheKey(): string
    {
        return 'openrouter_calls:'.now()->format('Y-m-d');
    }
}
