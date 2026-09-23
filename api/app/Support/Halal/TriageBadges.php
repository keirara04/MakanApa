<?php

namespace App\Support\Halal;

/**
 * Human-readable advisory badges + "why this priority" line from a stored triage summary and
 * priority breakdown. Shared by Filament and the admin API so both say the same thing.
 */
final class TriageBadges
{
    /** @return list<array{label: string, tone: string}> */
    public static function badges(?array $triage): array
    {
        $a = $triage['answers'] ?? null;
        if (! $a) {
            return [];
        }

        $badges = [];
        $p = fn (string $id) => (float) ($a[$id]['probability'] ?? 0);

        if ($p('mentions_certificate') >= 0.5) {
            $badges[] = ['label' => sprintf('Mentions certificate %.2f', $p('mentions_certificate')), 'tone' => 'success'];
        }
        if ($p('mentions_pork_alcohol') >= 0.5) {
            $badges[] = ['label' => sprintf('Mentions pork/alcohol %.2f', $p('mentions_pork_alcohol')), 'tone' => 'danger'];
        }
        if ($p('is_spam_or_irrelevant') >= 0.5) {
            $badges[] = ['label' => sprintf('Likely spam %.2f', $p('is_spam_or_irrelevant')), 'tone' => 'danger'];
        }
        if (isset($a['supports_claim']['score'])) {
            $badges[] = ['label' => sprintf('Evidence strength %.1f/%d', $a['supports_claim']['score'], $a['supports_claim']['maxLevel'] ?? 3), 'tone' => 'gray'];
        }
        if (($a['claim_vs_listing']['choice'] ?? null) === 'contradicts') {
            $badges[] = ['label' => 'Claim contradicts listing', 'tone' => 'warning'];
        }

        return $badges;
    }

    public static function priorityExplanation(?array $breakdown): ?string
    {
        if (! $breakdown) {
            return null;
        }
        $parts = ['base '.$breakdown['base']];
        foreach ([...($breakdown['rules'] ?? []), ...($breakdown['ai'] ?? [])] as $name => $points) {
            $parts[] = str_replace('_', ' ', $name).' '.($points >= 0 ? '+' : '').$points;
        }
        if (($breakdown['aiTotal'] ?? 0) !== array_sum($breakdown['ai'] ?? [])) {
            $parts[] = 'AI capped at '.($breakdown['aiTotal'] >= 0 ? '+' : '').$breakdown['aiTotal'];
        }

        return implode(', ', $parts).' → '.$breakdown['final'];
    }
}
