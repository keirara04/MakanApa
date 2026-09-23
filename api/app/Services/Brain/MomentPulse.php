<?php

namespace App\Services\Brain;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * What the user seems to be feeling *now*: this session's feedback at full strength plus
 * recent feedback outside the session, fading with a 12-hour half-life. Built from the last few
 * taste_events on every request — never stored, so it can't go stale.
 */
final readonly class MomentPulse
{
    /**
     * @param  array<string, array<string, float>>  $affinity  dimension => key => delta
     */
    public function __construct(
        public array $affinity,
        public float $closeness,
        public float $noveltyDrive,
        public int $sessionActions,
        public int $sessionRejections,
    ) {}

    public static function none(): self
    {
        return new self([], 0.0, 0.0, 0, 0);
    }

    /**
     * @param  iterable<array<string, mixed>>  $events  newest-first rows
     */
    public static function fromEvents(iterable $events, ?string $sessionId, CarbonInterface $now): self
    {
        $halfLife = (float) Config::get('brain.pulse.half_life_hours', 12);
        $outside = (float) Config::get('brain.pulse.outside_session_factor', 0.5);
        $minFactor = (float) Config::get('brain.pulse.min_factor', 0.02);

        $affinity = [];
        $closeness = 0.0;
        $novelty = 0.0;
        $actions = [];
        $rejections = 0;

        foreach ($events as $event) {
            if ($event['signal'] === 'reset') {
                break; // nothing older than a reset counts, same boundary as Selera Memory
            }
            if (($event['scope'] ?? 'both') === 'long' || in_array($event['signal'], ['trait_feedback', 'trait_mute'], true)) {
                continue;
            }

            $inSession = $sessionId !== null && ($event['session_id'] ?? null) === $sessionId;
            $ageHours = max(0, Carbon::parse($event['created_at'])->diffInSeconds($now, false)) / 3600;
            $factor = $inSession ? 1.0 : $outside * (0.5 ** ($ageHours / $halfLife));
            if ($factor < $minFactor) {
                continue;
            }

            if ($inSession && in_array($event['signal'], ['reroll', 'tune'], true)) {
                $actions[$event['signal'].':'.($event['decision_recommendation_id'] ?? $event['id'] ?? '')] = true;
                $rejections += $event['signal'] === 'reroll' && ($event['dimension'] ?? '') === 'category' ? 1 : 0;
            }

            $value = (float) $event['value'];
            $key = (string) ($event['dimension_key'] ?? '');

            match ($event['dimension']) {
                'category', 'cuisine', 'price' => $affinity[$event['dimension']][$key] = ($affinity[$event['dimension']][$key] ?? 0) + $value * $factor,
                'novelty' => $novelty += $value * $factor,
                'distance' => $key === 'pull' ? $closeness += $factor : null,
                default => null,
            };
        }

        return new self($affinity, $closeness, $novelty, count($actions), $rejections);
    }

    public function isEmpty(): bool
    {
        return $this->affinity === [] && $this->closeness == 0.0 && $this->noveltyDrive == 0.0;
    }

    public function delta(string $dimension, ?string $key): float
    {
        return $key === null ? 0.0 : ($this->affinity[$dimension][$key] ?? 0.0);
    }

    public function toArray(): array
    {
        return [
            'affinity' => $this->affinity,
            'closeness' => round($this->closeness, 3),
            'noveltyDrive' => round($this->noveltyDrive, 3),
            'sessionActions' => $this->sessionActions,
        ];
    }
}
