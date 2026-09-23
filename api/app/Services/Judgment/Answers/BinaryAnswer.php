<?php

namespace App\Services\Judgment\Answers;

use App\Services\Judgment\Statistics;

final class BinaryAnswer extends Answer
{
    /** @param list<float> $sampleValues per-sample p_yes */
    public function __construct(
        public readonly float $probability,
        public readonly array $sampleValues,
        public readonly float $sampleStdDev,
    ) {}

    /** @param list<float> $samples */
    public static function fromSamples(array $samples): self
    {
        $samples = array_map(fn ($v) => max(0.0, min(1.0, (float) $v)), $samples);

        return new self(Statistics::mean($samples), $samples, Statistics::stdDev($samples));
    }

    public function sampleCount(): int
    {
        return count($this->sampleValues);
    }

    /** 0 at p=0.5 (a coin flip), 1 at p=0 or 1 — "how decided", not "how correct". */
    public function decisiveness(): float
    {
        return abs($this->probability - 0.5) * 2;
    }

    public function toArray(): array
    {
        return [
            'type' => 'binary',
            'probability' => round($this->probability, 4),
            'sampleCount' => $this->sampleCount(),
            'sampleValues' => array_map(fn ($v) => round($v, 4), $this->sampleValues),
            'sampleStdDev' => round($this->sampleStdDev, 4),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self((float) $data['probability'], $data['sampleValues'] ?? [], (float) ($data['sampleStdDev'] ?? 0));
    }
}
