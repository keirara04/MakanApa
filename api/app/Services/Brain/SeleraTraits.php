<?php

namespace App\Services\Brain;

use App\Models\TasteEvent;
use App\Models\TasteProfile;
use App\Models\User;
use App\Support\RecommendationHeadline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Turns a Selera profile into a handful of human traits, each with *evidence* the user can
 * audit ("7 of your last 11 accepted picks were rice-based"). Taste only — hard requirements
 * like halal are Constraints and live in their own block, never as a "trait".
 */
class SeleraTraits
{
    private const CATEGORY_ICONS = [
        'nasi' => '🍚', 'rice' => '🍚', 'noodles' => '🍜', 'ramen' => '🍜', 'burger' => '🍔', 'chicken' => '🍗',
        'pizza' => '🍕', 'sushi' => '🍣', 'seafood' => '🦐', 'cafe' => '☕', 'dessert' => '🍰', 'bakery' => '🥐',
        'drinks' => '🧋', 'breakfast' => '🍳', 'bbq' => '🔥', 'steak' => '🥩', 'mamak' => '🫓', 'fast_food' => '🍟',
    ];

    private const SLOT_ICONS = ['breakfast' => '🌅', 'lunch' => '☀️', 'teatime' => '🫖', 'dinner' => '🌆', 'supper' => '🌙', 'weekend' => '🎉'];

    /**
     * @return array{stage: string, signalCount: int, traits: array<int, array>, constraints: array<int, array>}
     */
    public function describe(?TasteProfile $profile, TasteOwner $owner, ?User $user): array
    {
        $signals = (int) ($profile?->signal_count ?? 0);

        return [
            'stage' => DecisionBrainState::stageFor($signals),
            'signalCount' => $signals,
            'traits' => $profile ? $this->traits($profile, $owner) : [],
            'constraints' => [
                ['key' => 'halal', 'label' => 'Hide non-halal', 'icon' => '✅', 'value' => (bool) $user?->halal_preference, 'editIn' => 'settings'],
                ['key' => 'budget', 'label' => 'Budget', 'icon' => '💰', 'value' => null, 'editIn' => 'each_decision'],
                ['key' => 'distance', 'label' => 'Distance', 'icon' => '📍', 'value' => null, 'editIn' => 'each_decision'],
            ],
        ];
    }

    private function traits(TasteProfile $profile, TasteOwner $owner): array
    {
        $now = Carbon::now();
        $memory = $profile->memory ?? [];
        $hidden = fn (string $key) => in_array($key, $profile->muted ?? [], true) || (($profile->corrections[$key] ?? null) === 'not_really');
        $accepts = $this->recentAcceptCategories($profile, $owner);
        $traits = [];

        // Top category / cuisine.
        foreach (['category', 'cuisine'] as $dim) {
            $top = $this->top($memory['longTerm'][$dim] ?? [], $now);
            if ($top && ! $hidden("{$dim}:{$top['key']}") && $top['effective'] >= 0.6 && $top['n'] >= 2) {
                $label = RecommendationHeadline::categoryLabel($top['key']);
                $count = count(array_filter($accepts, fn ($c) => $c === $top['key']));
                $traits[] = $this->trait("{$dim}:{$top['key']}", self::CATEGORY_ICONS[$top['key']] ?? '🍽️', "{$label} person", $top['n'],
                    $dim === 'category' && $accepts ? sprintf('%d of your last %d accepted picks were %s', $count, count($accepts), mb_strtolower($label)) : sprintf('You keep going for %s', mb_strtolower($label)));
                break;
            }
        }

        // Price band.
        $price = $this->top($memory['longTerm']['price'] ?? [], $now);
        if ($price && ! $hidden("price:{$price['key']}") && $price['effective'] >= 0.6) {
            [$icon, $label] = match (true) {
                (int) $price['key'] <= 1 => ['💸', 'Budget-conscious'],
                (int) $price['key'] === 2 => ['🪙', 'Mid-range regular'],
                default => ['✨', 'Likes to treat yourself'],
            };
            $traits[] = $this->trait("price:{$price['key']}", $icon, $label, $price['n'], 'Based on the price range you usually accept');
        }

        // Distance habit.
        $distance = $memory['distance'] ?? [];
        if (($distance['ewma'] ?? null) !== null && ($distance['n'] ?? 0) >= 3 && ! $hidden('distance:close')) {
            $traits[] = $this->trait('distance:close', '📍', sprintf('Usually stays within %.1f km', $distance['ewma']), $distance['n'], sprintf('Averaged over your last %d picks', $distance['n']));
        }

        // Meal-slot habits ("Supper = mamak").
        foreach ($memory['slots'] ?? [] as $slot => $slice) {
            $top = $this->top($slice['category'] ?? [], $now);
            if ($top && $top['n'] >= (int) Config::get('brain.slot_min_evidence', 4) && $top['effective'] > 0.4) {
                $key = "slot:{$slot}:{$top['key']}";
                if (! $hidden($key)) {
                    $label = mb_strtolower((string) RecommendationHeadline::categoryLabel($top['key']));
                    $traits[] = $this->trait($key, self::SLOT_ICONS[$slot] ?? '🕐', ucfirst($slot)." = {$label}", $top['n'], "You picked {$label} {$top['n']} times at {$slot}");
                }
            }
        }

        // Explorer vs loyalist.
        $recent = array_column($memory['recent'] ?? [], 'category');
        if (count($recent) >= 5 && ! $hidden('style:explorer') && ! $hidden('style:loyalist')) {
            $ratio = count(array_unique($recent)) / count($recent);
            if ($ratio >= 0.7) {
                $traits[] = $this->trait('style:explorer', '🎲', 'Explorer', count($recent), sprintf('%d different kinds of food in your last %d picks', count(array_unique($recent)), count($recent)));
            } elseif ($ratio <= 0.35) {
                $traits[] = $this->trait('style:loyalist', '🔁', 'Loyalist', count($recent), 'You know what you like and stick to it');
            }
        }

        // Vibe.
        $vibe = $this->top($memory['longTerm']['vibe'] ?? [], $now);
        if ($vibe && ! $hidden("vibe:{$vibe['key']}") && $vibe['n'] >= 2) {
            $traits[] = $this->trait("vibe:{$vibe['key']}", '🪑', ucwords(str_replace('_', ' ', $vibe['key'])).' spots', $vibe['n'], "You tagged {$vibe['n']} places like this");
        }

        return array_slice($traits, 0, 6);
    }

    private function trait(string $key, string $icon, string $label, int $evidence, string $why): array
    {
        $confidence = TasteMemory::confidence($evidence);

        return [
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'strength' => $confidence >= 0.7 ? 'strong' : ($confidence >= 0.45 ? 'medium' : 'emerging'),
            'evidence' => $why,
        ];
    }

    /** @return array{key: string, effective: float, n: int}|null */
    private function top(array $entries, Carbon $now): ?array
    {
        $best = null;
        foreach ($entries as $key => $entry) {
            $effective = TasteMemory::effective($entry, $now);
            if ($best === null || $effective > $best['effective']) {
                $best = ['key' => (string) $key, 'effective' => $effective, 'n' => (int) $entry['n']];
            }
        }

        return $best;
    }

    /** @return string[] categories of recent accepts, after the latest reset */
    private function recentAcceptCategories(TasteProfile $profile, TasteOwner $owner): array
    {
        return $owner->scope(TasteEvent::query())
            ->whereIn('signal', TasteMemory::ACCEPT_SIGNALS)
            ->where('dimension', 'category')
            ->when($profile->reset_at_event_id, fn ($q) => $q->where('id', '>', $profile->reset_at_event_id))
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('dimension_key')
            ->all();
    }
}
