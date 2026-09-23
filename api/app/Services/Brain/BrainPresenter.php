<?php

namespace App\Services\Brain;

use App\Models\Decision;
use App\Models\DecisionRecommendation;

/**
 * Wire format for Makan Brain's extra recommendation keys. All optional on the client —
 * a v1 decision (or an older app build) simply doesn't get / ignores them.
 */
final class BrainPresenter
{
    /**
     * @param  array{category: ?string}|null  $rejected  the candidate just rerolled away from, if any
     */
    public static function forRow(Decision $decision, DecisionRecommendation $row, bool $withTrace, ?array $rejected = null, ?string $lead = null): array
    {
        $facts = $row->reason_facts ?? [];
        $category = $row->breakdown['facts']['category'] ?? null;
        $changed = $rejected !== null && ($rejected['category'] ?? null) !== null && $rejected['category'] !== $category;

        $rendered = ReasonCatalog::render($facts, (int) $decision->id, $changed, $rejected['category'] ?? null);
        if ($lead !== null) {
            array_unshift($rendered['reasons'], ['family' => 'match', 'key' => 'tune', 'icon' => '🎯', 'text' => $lead]);
            $rendered['reasons'] = array_slice($rendered['reasons'], 0, 3);
        }

        $context = $decision->context_snapshot ?? [];

        return [
            'reasons' => $rendered['reasons'],
            'decidingFactor' => $rendered['decidingFactor'],
            'fit' => $rendered['fit'],
            'thinkingTrace' => $withTrace ? ReasonCatalog::thinkingTrace($decision->funnel ?? [], $decision->intent_type ?? 'anything', $decision->budget_max !== null) : [],
            'context' => $context ? ContextEngine::present(new ContextSnapshot(
                $context['mealSlot'] ?? 'anytime', $context['localTime'] ?? '', $context['signals'] ?? [],
                $context['generatedAt'] ?? '', $context['weatherAgeMinutes'] ?? null, $context['weatherSource'] ?? null,
            )) : [],
            'hasWhatIf' => ($facts['whatIf'] ?? 0) > 0,
            'canTune' => (bool) config('brain.features.tune') && count($decision->tunes ?? []) < (int) config('brain.tune.max_per_decision', 2),
        ];
    }
}
