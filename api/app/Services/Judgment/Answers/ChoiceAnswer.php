<?php

namespace App\Services\Judgment\Answers;

use App\Services\Judgment\Statistics;

final class ChoiceAnswer extends Answer
{
    /**
     * @param  array<string, float>  $probabilities  averaged over samples
     * @param  list<string>  $sampleChoices  per-sample argmax
     */
    public function __construct(
        public readonly string $choice,
        public readonly array $probabilities,
        public readonly float $confidence,
        public readonly float $margin,
        public readonly float $voteShare,
        public readonly array $sampleChoices,
    ) {}

    /** @param list<array<string, float>> $distributions normalized per-sample distributions */
    public static function fromSamples(array $distributions): self
    {
        $avg = Statistics::averageDistributions($distributions);
        $choice = Statistics::argmax($avg);
        $sampleChoices = array_map(fn ($d) => Statistics::argmax($d), $distributions);
        $votes = count(array_filter($sampleChoices, fn ($c) => $c === $choice));

        return new self(
            $choice,
            $avg,
            Statistics::concentration($avg),
            Statistics::margin($avg),
            $votes / count($distributions),
            $sampleChoices,
        );
    }

    public function probabilityOf(string $option): float
    {
        return $this->probabilities[$option] ?? 0.0;
    }

    public function toArray(): array
    {
        return [
            'type' => 'choice',
            'choice' => $this->choice,
            'probabilities' => array_map(fn ($v) => round($v, 4), $this->probabilities),
            'confidence' => round($this->confidence, 4),
            'margin' => round($this->margin, 4),
            'voteShare' => round($this->voteShare, 4),
            'sampleCount' => count($this->sampleChoices),
            'sampleChoices' => $this->sampleChoices,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self($data['choice'], $data['probabilities'], (float) $data['confidence'], (float) ($data['margin'] ?? 0), (float) ($data['voteShare'] ?? 1), $data['sampleChoices'] ?? []);
    }
}
