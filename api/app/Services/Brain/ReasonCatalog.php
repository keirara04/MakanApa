<?php

namespace App\Services\Brain;

use App\Support\RecommendationHeadline;

/**
 * Stored reason facts → light-Manglish sentences, at response time. The variant is chosen by
 * crc32(seed.key), so a decision always reads the same way on reload but different decisions
 * don't sound copy-pasted. Bump `brain.reason_catalog_version` when the wording changes in a
 * way analysis should be able to tell apart.
 */
final class ReasonCatalog
{
    private const ICONS = [
        'craving_match' => '🔥', 'mood_match' => '😋', 'selera_slot' => '🌙', 'pulse' => '💭', 'selera_match' => '🍛',
        'lens_cheap_today' => '💸', 'lens_treat_myself' => '✨', 'lens_surprise_me' => '🎲', 'lens_quick_one' => '⚡', 'lens_community_favs' => '👥',
        'close' => '📍', 'rating' => '⭐', 'budget_fit' => '💸', 'community_picks' => '👥', 'hidden_gem' => '💎', 'popular' => '📈', 'halal_verified' => '✅',
        'ctx_rain' => '☔', 'ctx_supper' => '🌙', 'ctx_friday' => '🕌', 'ctx_iftar' => '🌅', 'ctx_sahur' => '🌙', 'ctx_month_end' => '💸',
        'novelty' => '🔄', 'fatigue' => '😮‍💨', 'wildcard' => '🎲', 'pulse_reject' => '👌',
    ];

    private const DECIDING = [
        'distance' => 'closest strong match',
        'relevance' => 'the best match for your craving',
        'mood' => 'the best fit for your mood',
        'rating' => 'the best rated of the bunch',
        'cheapEatsFit' => 'the easiest on the wallet',
        'budget' => 'fits your budget',
        'community' => 'what your community keeps picking',
        'personalFit' => 'your Selera',
        'novelty' => 'something different from your usual',
        'openCertainty' => 'definitely open right now',
        'lateNightFit' => 'open late',
        'popularityBonus' => 'a proven crowd favourite',
        'reviewVolumeBonus' => 'a low-key hidden gem',
        'cafeRelevance' => 'the best café vibe',
        'vibeRelevance' => 'the vibe you asked for',
        'halalConfidence' => 'halal confidence',
        'exploration' => 'a wildcard — you\'ve been playing safe',
    ];

    /**
     * @return array{reasons: array<int, array{family: string, key: string, icon: string, text: string}>, decidingFactor: ?string, fit: string}
     */
    public static function render(array $facts, int $seed, bool $rejectedCategoryChanged = false, ?string $rejectedCategory = null): array
    {
        $reasons = [];
        if ($rejectedCategoryChanged && $rejectedCategory) {
            $reasons[] = self::line('moment', 'pulse_reject', $seed, ['category' => $rejectedCategory]);
        }
        foreach ($facts['reasons'] ?? [] as $reason) {
            if ($rejectedCategoryChanged && $reason['family'] === 'moment') {
                continue; // one MOMENT line only — the "not feeling X" acknowledgement wins
            }
            $line = self::line($reason['family'], $reason['key'], $seed, $reason['facts'] ?? []);
            if ($line !== null) {
                $reasons[] = $line;
            }
        }

        $deciding = $facts['decidingFactor']['component'] ?? null;

        return [
            'reasons' => array_values(array_filter($reasons)),
            'decidingFactor' => $deciding && isset(self::DECIDING[$deciding]) ? 'Deciding factor: '.self::DECIDING[$deciding].'.' : null,
            'fit' => $facts['fit'] ?? 'good',
        ];
    }

    /** "Checked 31 nearby spots → Removed 7 closed → …" — the real funnel, not loading copy. */
    public static function thinkingTrace(array $funnel, string $intentType, bool $hasBudget): array
    {
        $lines = [sprintf('Checked %d nearby spot%s', $funnel['checked'] ?? 0, ($funnel['checked'] ?? 0) === 1 ? '' : 's')];
        if (($funnel['closed'] ?? 0) > 0) {
            $lines[] = "Removed {$funnel['closed']} closed";
        }
        if (($funnel['non_halal'] ?? 0) > 0) {
            $lines[] = "Removed {$funnel['non_halal']} non-halal";
        }
        if ($hasBudget && ($funnel['over_budget'] ?? 0) > 0) {
            $lines[] = sprintf('%d within budget', $funnel['eligible'] ?? 0);
        } elseif (($funnel['too_far'] ?? 0) > 0) {
            $lines[] = sprintf('%d close enough', $funnel['eligible'] ?? 0);
        }
        if ($intentType !== 'anything' && isset($funnel['strong_match'])) {
            $lines[] = sprintf('%d strongly matched your %s', $funnel['strong_match'], $intentType === 'craving' ? 'craving' : 'mood');
        }
        if (($funnel['diversified'] ?? 0) > 0) {
            $lines[] = 'Mixed it up so it\'s not all the same';
        }
        $lines[] = 'Picked this one';

        return $lines;
    }

    private static function line(string $family, string $key, int $seed, array $f): ?array
    {
        $variants = self::variants($key, $f);
        if ($variants === []) {
            return null;
        }

        return [
            'family' => $family,
            'key' => $key,
            'icon' => self::ICONS[$key] ?? '•',
            'text' => $variants[crc32($seed.$key) % count($variants)],
        ];
    }

    /** @return string[] */
    private static function variants(string $key, array $f): array
    {
        $cat = RecommendationHeadline::categoryLabel($f['category'] ?? null);
        $catLower = $cat ? mb_strtolower($cat) : null;
        $distance = isset($f['distanceM']) ? self::distance($f['distanceM']) : null;
        $pool = $f['poolSize'] ?? null;
        $community = $f['community'] ?? null;

        return match ($key) {
            'craving_match' => ["Exactly your {$f['craving']} craving", "Your {$f['craving']} craving, sorted"],
            'mood_match' => ['Matches the mood you picked', 'Fits exactly what you felt like'],
            'selera_slot' => $catLower ? [ucfirst($f['slot']).' = '.$catLower.', as usual', "Your usual {$f['slot']} go-to: {$catLower}"] : [],
            'pulse' => $catLower ? ["You've been into {$catLower} lately", "Riding your {$catLower} wave"] : [],
            'selera_match' => $catLower ? ["Your kind of place — you love {$catLower}", "Very your Selera: {$catLower}"] : ['Very your Selera'],
            'lens_cheap_today' => ['Cheap today — '.(self::price($f['priceLevel'] ?? null) ?? 'easy on the wallet')],
            'lens_treat_myself' => ['Treat yourself — you deserve it ✨'],
            'lens_surprise_me' => ['Surprise! Something you wouldn\'t usually pick'],
            'lens_quick_one' => ['Quick one — '.($distance ?? 'close by').' and ready to go'],
            'lens_community_favs' => [($community ? "{$community} favourite" : 'Local favourite').' right now'],
            'close' => $f['rank'] === 1 && $pool > 1
                ? ["{$distance} — closest one that matched", "Closest of {$pool} options · {$distance}"]
                : ["Only {$distance} away"],
            'rating' => $f['rank'] === 1 && $pool > 1
                ? [sprintf('Best rated of the nearby matches (%.1f★)', $f['rating']), sprintf('%.1f★ — top rated of %d', $f['rating'], $pool)]
                : [sprintf('Solid %.1f★ rating', $f['rating'])],
            'budget_fit' => $f['rank'] === 1 && $pool > 1
                ? ["Cheapest of your top {$pool}", 'Lightest on the wallet here']
                : ['Easy on the wallet'],
            'community_picks' => $community
                ? ["{$f['pickers']} {$community} people makan here this week", "{$community} keeps coming back here ({$f['pickers']} this week)"]
                : ["{$f['pickers']} people nearby picked this lately"],
            'hidden_gem' => ['Hidden gem — few reviews, high rating', 'Low-key spot the crowd hasn\'t found yet'],
            'popular' => ['A proven crowd favourite'],
            'halal_verified' => ['Halal certified'],
            'ctx_rain' => ['Hujan — kept it close ☔', 'Raining, so nothing far'],
            'ctx_supper' => ['Still open for supper 🌙', 'Supper sorted — open now'],
            'ctx_friday' => ['Jumaat — picked one that\'s open'],
            'ctx_iftar' => ['Iftar soon — close by'],
            'ctx_sahur' => ['Open for sahur'],
            'ctx_month_end' => ['Hujung bulan — kept it cheap 💸'],
            'novelty' => ($s = RecommendationHeadline::categoryLabel($f['streak'] ?? null))
                ? ['Something different from your '.mb_strtolower($s).' streak', 'Break from all that '.mb_strtolower($s)]
                : [],
            'fatigue' => ['Okay lah, enough choosing 😭 — this is the safest bet'],
            'wildcard' => ['Bit of a wildcard — you\'ve been playing safe', 'Wildcard pick — trust me on this one'],
            'pulse_reject' => $catLower ? ["Not feeling {$catLower}? Trying something different", "No {$catLower} then — how about this"] : ['Okay, trying something different'],
            default => [],
        };
    }

    private static function distance(int $meters): string
    {
        return $meters < 1000 ? (int) (round($meters / 50) * 50).' m' : sprintf('%.1f km', $meters / 1000);
    }

    private static function price(?int $level): ?string
    {
        return match ($level) {
            0, 1 => 'under RM15',
            2 => 'around RM15–30',
            3 => 'around RM30–60',
            4 => 'RM60 and up',
            default => null,
        };
    }
}
