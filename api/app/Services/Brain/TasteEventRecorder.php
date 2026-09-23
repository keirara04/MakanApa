<?php

namespace App\Services\Brain;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\TasteEvent;
use App\Models\User;
use App\Support\TuneDirection;
use App\Support\WhyNotReason;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Turns one logical feedback signal into taste_events rows (one per dimension it touches),
 * inserts them idempotently, then catches the profile up. Authority tiers scale every value:
 * explicit feedback outranks an accept, which outranks a tune, which outranks a reroll.
 * Passive interactions (detail opened, directions tapped) never come through here.
 */
class TasteEventRecorder
{
    public function __construct(private readonly TasteProfileBuilder $builder) {}

    public function accept(Decision $decision, DecisionRecommendation $row, ?User $user): void
    {
        $restaurant = $row->restaurant()->with('cuisines')->first();
        if (! $restaurant) {
            return;
        }

        $distanceKm = $row->breakdown['facts']['distanceKm'] ?? null;
        $rows = [
            ...$this->tasteRows('accept', $restaurant->food_category, $restaurant->cuisines->pluck('slug')->all(), $restaurant->price_level, 1.0, 'both', 'strong'),
        ];
        if ($distanceKm !== null) {
            $rows[] = ['dimension' => 'distance', 'dimension_key' => 'km', 'value' => (float) $distanceKm, 'scope' => 'long', 'authority' => 'strong'];
        }

        $this->write($decision, $row, $user, 'accept', null, 'behaviour', $rows);
    }

    /**
     * "Makan sini" from search — the user named what they wanted and picked it themselves, so it
     * teaches like an accept at explicit authority. TasteMemory treats it as an accept for slots
     * and the recent-categories ring.
     */
    public function searchChoose(Decision $decision, DecisionRecommendation $row, ?User $user): void
    {
        $restaurant = $row->restaurant()->with('cuisines')->first();
        if (! $restaurant) {
            return;
        }

        $distanceKm = $row->breakdown['facts']['distanceKm'] ?? null;
        $rows = $this->tasteRows('search_choose', $restaurant->food_category, $restaurant->cuisines->pluck('slug')->all(), $restaurant->price_level, 1.0, 'both', 'explicit');
        if ($distanceKm !== null) {
            $rows[] = ['dimension' => 'distance', 'dimension_key' => 'km', 'value' => (float) $distanceKm, 'scope' => 'long', 'authority' => 'explicit'];
        }

        $this->write($decision, $row, $user, 'search_choose', null, 'explicit', $rows);
    }

    public function reroll(Decision $decision, DecisionRecommendation $rejected, ?User $user): void
    {
        $restaurant = $rejected->restaurant()->with('cuisines')->first();
        if (! $restaurant) {
            return;
        }

        $rows = $this->tasteRows('reroll', $restaurant->food_category, $restaurant->cuisines->pluck('slug')->all(), null, -1.0, 'pulse', 'weak');
        $this->write($decision, $rejected, $user, 'reroll', null, 'behaviour', $rows ?: [['dimension' => 'novelty', 'dimension_key' => '', 'value' => 0.0, 'scope' => 'pulse', 'authority' => 'weak']]);
    }

    public function whyNot(Decision $decision, DecisionRecommendation $rejected, ?User $user, WhyNotReason $reason, ?string $detail): void
    {
        $restaurant = $rejected->restaurant()->with('cuisines')->first();
        if (! $restaurant) {
            return;
        }
        $category = $restaurant->food_category;
        $cuisines = $restaurant->cuisines->pluck('slug')->all();
        $e = 'explicit';

        $rows = match (true) {
            $reason === WhyNotReason::TooFar => [
                ['dimension' => 'distance', 'dimension_key' => 'pull', 'value' => 0.85, 'scope' => 'both', 'authority' => $e],
            ],
            $reason === WhyNotReason::TooPricey => $restaurant->price_level === null ? [] : [
                ['dimension' => 'price', 'dimension_key' => (string) $restaurant->price_level, 'value' => -0.6, 'scope' => 'both', 'authority' => $e],
            ],
            $reason === WhyNotReason::AteRecently => [
                ...$this->tasteRows('why_not', $category, [], null, -0.5, 'pulse', $e),
                ['dimension' => 'novelty', 'dimension_key' => '', 'value' => 0.5, 'scope' => 'pulse', 'authority' => $e],
            ],
            $detail === 'dont_like_cuisine' => [
                ...$this->tasteRows('why_not', null, $cuisines, null, -1.0, 'long', $e),
                ...$this->tasteRows('why_not', $category, [], null, -0.4, 'long', $e),
            ],
            $detail === 'too_similar' => [
                ...$this->tasteRows('why_not', $category, [], null, -0.3, 'pulse', $e),
                ['dimension' => 'novelty', 'dimension_key' => '', 'value' => 0.5, 'scope' => 'pulse', 'authority' => $e],
            ],
            $detail === 'too_heavy' => $this->tasteRows('why_not', $category, [], null, -0.3, 'pulse', $e),
            // not_feeling_it with no detail, or just_not_today: today only — never long-term.
            default => $this->tasteRows('why_not', $category, [], null, -0.4, 'pulse', $e),
        };

        $rejected->update(['reject_reason' => $detail ? "{$reason->value}:{$detail}" : $reason->value]);
        $this->write($decision, $rejected, $user, 'why_not', $detail ?? $reason->value, 'explicit', $rows);
    }

    public function tune(Decision $decision, DecisionRecommendation $current, ?User $user, TuneDirection $direction): void
    {
        $restaurant = $current->restaurant;
        $rows = match ($direction) {
            TuneDirection::Closer => [['dimension' => 'distance', 'dimension_key' => 'pull', 'value' => 0.9, 'scope' => 'pulse', 'authority' => 'medium']],
            TuneDirection::Cheaper => $restaurant?->price_level === null ? [] : [['dimension' => 'price', 'dimension_key' => (string) $restaurant->price_level, 'value' => -0.5, 'scope' => 'pulse', 'authority' => 'medium']],
            TuneDirection::Safer => [['dimension' => 'novelty', 'dimension_key' => '', 'value' => -0.5, 'scope' => 'pulse', 'authority' => 'medium']],
            TuneDirection::Adventurous => [['dimension' => 'novelty', 'dimension_key' => '', 'value' => 0.5, 'scope' => 'pulse', 'authority' => 'medium']],
        };

        $this->write($decision, $current, $user, 'tune', $direction->value, 'explicit', $rows ?: [['dimension' => 'novelty', 'dimension_key' => '', 'value' => 0.0, 'scope' => 'pulse', 'authority' => 'medium']]);
    }

    public function vibeTag(Decision $decision, DecisionRecommendation $row, ?User $user, string $tag): void
    {
        $this->write($decision, $row, $user, 'vibe_tag', $tag, 'explicit', [
            ['dimension' => 'vibe', 'dimension_key' => $tag, 'value' => 0.6, 'scope' => 'long', 'authority' => 'medium'],
        ]);
    }

    /** Selera page corrections: "Not really" / "More like this" / "Less like this". */
    public function traitFeedback(TasteOwner $owner, string $traitKey, string $kind): void
    {
        $value = match ($kind) {
            'more' => 0.75,
            'less' => -0.75,
            default => -1.0, // not_really
        };

        $this->insertAndCatchUp($owner, [[
            ...$this->ownerColumns($owner), 'session_id' => null, 'signal' => 'trait_feedback', 'detail' => $kind,
            'dimension' => 'trait', 'dimension_key' => $traitKey, 'value' => $value * $this->authority('explicit'),
            'scope' => 'long', 'source' => 'explicit', 'authority' => 'explicit', 'metadata' => json_encode(['primary' => true]),
        ]]);
    }

    public function traitMute(TasteOwner $owner, string $traitKey): void
    {
        $this->insertAndCatchUp($owner, [[
            ...$this->ownerColumns($owner), 'session_id' => null, 'signal' => 'trait_mute', 'detail' => null,
            'dimension' => 'trait', 'dimension_key' => $traitKey, 'value' => 0, 'scope' => 'long',
            'source' => 'explicit', 'authority' => 'explicit', 'metadata' => null,
        ]]);
    }

    /** A boundary, not a delete — history stays auditable, replay starts after it. */
    public function reset(TasteOwner $owner): void
    {
        $this->insertAndCatchUp($owner, [[
            ...$this->ownerColumns($owner), 'session_id' => null, 'signal' => 'reset', 'detail' => null,
            'dimension' => 'boundary', 'dimension_key' => '', 'value' => 0, 'scope' => 'both',
            'source' => 'explicit', 'authority' => 'explicit', 'metadata' => null,
        ]]);
    }

    // ── internals ────────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function tasteRows(string $signal, ?string $category, array $cuisines, ?int $priceLevel, float $sign, string $scope, string $authority): array
    {
        $rows = [];
        if ($category) {
            $rows[] = ['dimension' => 'category', 'dimension_key' => $category, 'value' => $sign, 'scope' => $scope, 'authority' => $authority];
        }
        foreach (array_slice($cuisines, 0, 3) as $cuisine) {
            $rows[] = ['dimension' => 'cuisine', 'dimension_key' => $cuisine, 'value' => $sign * 0.7, 'scope' => $scope, 'authority' => $authority];
        }
        if ($priceLevel !== null) {
            $rows[] = ['dimension' => 'price', 'dimension_key' => (string) $priceLevel, 'value' => $sign, 'scope' => $scope === 'both' ? 'long' : $scope, 'authority' => $authority];
        }

        return $rows;
    }

    private function write(Decision $decision, DecisionRecommendation $row, ?User $user, string $signal, ?string $detail, string $source, array $rows): void
    {
        $owner = TasteOwner::resolve($user ?? $decision->user, $decision->installation_id);
        if ($owner === null || $rows === []) {
            return;
        }

        $now = Carbon::now();
        $local = $now->copy()->setTimezone(Config::get('brain.timezone'));
        $slot = ContextEngine::slotFor($local, false);
        $weekend = $local->isWeekend();

        $records = [];
        foreach (array_values($rows) as $index => $r) {
            $records[] = [
                ...$this->ownerColumns($owner),
                'session_id' => $decision->session_id,
                'decision_id' => $decision->id,
                'decision_recommendation_id' => $row->id,
                'restaurant_id' => $row->restaurant_id,
                'signal' => $signal,
                'detail' => $detail,
                'dimension' => $r['dimension'],
                'dimension_key' => $r['dimension_key'],
                // distance "km"/"pull" values are measurements/factors, not preferences — unscaled.
                'value' => $r['dimension'] === 'distance' ? $r['value'] : $r['value'] * $this->authority($r['authority']),
                'scope' => $r['scope'],
                'source' => $source,
                'authority' => $r['authority'],
                'slot' => $slot,
                'metadata' => json_encode(array_filter(['primary' => $index === 0, 'weekend' => $weekend])),
                'created_at' => $now,
            ];
        }

        $this->insertAndCatchUp($owner, $records);
    }

    private function insertAndCatchUp(TasteOwner $owner, array $records): void
    {
        foreach ($records as &$record) {
            $record['created_at'] ??= Carbon::now();
        }

        // insertOrIgnore + the (decision_recommendation_id, signal, dimension, key) unique index:
        // a retried call inserts nothing and the catch-up below is then a no-op.
        if (TasteEvent::query()->insertOrIgnore($records) > 0) {
            $this->builder->catchUp($owner);
        }
    }

    private function ownerColumns(TasteOwner $owner): array
    {
        return ['user_id' => $owner->userId, 'installation_id' => $owner->installationId];
    }

    private function authority(string $tier): float
    {
        return (float) Config::get("brain.authority.{$tier}", 1.0);
    }
}
