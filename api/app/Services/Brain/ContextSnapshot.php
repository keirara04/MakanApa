<?php

namespace App\Services\Brain;

/**
 * "Right now" for one request. Each signal carries its own confidence (rules: 1.0; weather:
 * fading with age), so a stale rain reading nudges less than a fresh one instead of flipping
 * a binary switch. Stored verbatim in decisions.context_snapshot for the Decision Trace.
 */
final readonly class ContextSnapshot
{
    /**
     * @param  array<string, array{active: bool, confidence: float, source: string, ageMinutes: ?int}>  $signals
     */
    public function __construct(
        public string $mealSlot,
        public string $localTime,
        public array $signals,
        public string $generatedAt,
        public ?int $weatherAgeMinutes,
        public ?string $weatherSource,
    ) {}

    public static function neutral(): self
    {
        return new self('any', now()->toIso8601String(), [], now()->toIso8601String(), null, null);
    }

    public function isActive(string $key): bool
    {
        return ($this->signals[$key]['active'] ?? false) === true;
    }

    public function confidence(string $key): float
    {
        return $this->isActive($key) ? (float) $this->signals[$key]['confidence'] : 0.0;
    }

    /** @return string[] */
    public function activeKeys(): array
    {
        return array_keys(array_filter($this->signals, fn ($s) => $s['active']));
    }

    public function toArray(): array
    {
        return [
            'mealSlot' => $this->mealSlot,
            'localTime' => $this->localTime,
            'signals' => $this->signals,
            'generatedAt' => $this->generatedAt,
            'weatherAgeMinutes' => $this->weatherAgeMinutes,
            'weatherSource' => $this->weatherSource,
        ];
    }
}
