<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Services\Judgment\Definitions\NonHalalSecondOpinionGate;
use App\Services\Judgment\Definitions\NonHalalSecondOpinionV1;
use App\Services\Judgment\JudgmentEngine;
use App\Support\Halal\HalalHeuristic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stores an AI "likely serves pork/alcohol" hint for admin review. Never writes the halal
 * ledger or snapshot. Idempotent per listing state (the definition re-judges on state change).
 */
class SecondOpinionNonHalal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $restaurantId) {}

    public function handle(JudgmentEngine $engine): void
    {
        $restaurant = Restaurant::find($this->restaurantId);
        if (! $restaurant) {
            return;
        }

        $heuristic = HalalHeuristic::evaluate([
            'name' => $restaurant->name,
            'signature_dish' => $restaurant->signature_dish,
            'food_category' => $restaurant->food_category,
            'google_types' => $restaurant->google_types,
        ]);
        // Re-checked at run time: the place may have been decided or changed since dispatch.
        if (! NonHalalSecondOpinionGate::shouldAsk($restaurant, $heuristic)) {
            return;
        }

        $result = $engine->ask(new NonHalalSecondOpinionV1, ['restaurant' => $restaurant, 'heuristic' => $heuristic], $restaurant);
        if ($result === null) {
            return;
        }

        $answer = $result->binary('likely_serves_pork_or_alcohol');
        $restaurant->forceFill(['halal_ai_hint' => [
            'probability' => round($answer->probability, 4),
            'stdDev' => round($answer->sampleStdDev, 4),
            'samples' => $answer->sampleCount(),
            'version' => $result->definitionVersion,
            'judgmentId' => $result->judgmentId,
            'runId' => $result->runId,
            'askedAt' => now()->toIso8601String(),
        ]])->save();
    }

    /** Throttle for sync paths: skip places with a fresh hint (the job itself is idempotent). */
    public static function dispatchIfDue(Restaurant $restaurant): void
    {
        $askedAt = $restaurant->halal_ai_hint['askedAt'] ?? null;
        if ($askedAt && now()->subDays(30)->lt($askedAt)) {
            return;
        }
        self::dispatch($restaurant->id);
    }
}
