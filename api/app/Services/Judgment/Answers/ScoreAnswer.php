<?php

namespace App\Services\Judgment\Answers;

use App\Services\Judgment\Statistics;

/** Probability-weighted position across levels: score = Σ level × p. */
final class ScoreAnswer extends Answer
{
    /**
     * @param  array<string, float>  $probabilities  level index => averaged probability
     * @param  list<float>  $sampleScores  per-sample expected score
     */
    public function __construct(
        public readonly float $score,
        public readonly int $maxLevel,
        public readonly array $probabilities,
        public readonly float $confidence,
        public readonly array $sampleScores,
        public readonly float $sampleStdDev,
    ) {}

    /** @param list<array<string, float>> $distributions */
    public static function fromSamples(array $distributions, int $maxLevel): self
    {
        $expected = fn (array $d) => array_sum(array_map(fn ($level, $p) => (int) $level * $p, array_keys($d), $d));
        $avg = Statistics::averageDistributions($distributions);
        $sampleScores = array_map($expected, $distributions);

        return new self($expected($avg), $maxLevel, $avg, Statistics::concentration($avg), $sampleScores, Statistics::stdDev($sampleScores));
    }

    /** Score scaled to 0-1 — used where a score is compared against a binary outcome. */
    public function normalized(): float
    {
        return $this->maxLevel > 0 ? $this->score / $this->maxLevel : 0.0;
    }

    public function toArray(): array
    {
        return [
            'type' => 'score',
            'score' => round($this->score, 4),
            'maxLevel' => $this->maxLevel,
            'probabilities' => array_map(fn ($v) => round($v, 4), $this->probabilities),
            'confidence' => round($this->confidence, 4),
            'sampleCount' => count($this->sampleScores),
            'sampleScores' => array_map(fn ($v) => round($v, 4), $this->sampleScores),
            'sampleStdDev' => round($this->sampleStdDev, 4),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self((float) $data['score'], (int) $data['maxLevel'], $data['probabilities'], (float) $data['confidence'], $data['sampleScores'] ?? [], (float) ($data['sampleStdDev'] ?? 0));
    }
}
