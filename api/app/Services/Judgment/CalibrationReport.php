<?php

namespace App\Services\Judgment;

use App\Models\AiJudgment;

/**
 * Compares logged predictions with outcome-derived truth for ONE purpose + definition version.
 * Shared by `judgments:calibration` and the Filament calibration page so both agree.
 */
final class CalibrationReport
{
    /**
     * @return array{purpose: string, version: int, labelled: int, questions: array<string, array>}
     */
    public static function build(string $purpose, ?int $version = null, ?string $since = null, float $threshold = 0.5): array
    {
        $version ??= DefinitionRegistry::latestVersion($purpose);
        $definition = DefinitionRegistry::get($purpose, $version);

        $rows = AiJudgment::where('purpose', $purpose)
            ->where('definition_version', $version)
            ->where('status', 'ok')
            ->whereNotNull('outcome')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->get(['answers', 'outcome']);

        $pairs = [];
        foreach ($rows as $row) {
            foreach ($definition->calibrationTargets($row->outcome) as $questionId => $truth) {
                $answer = $row->answers[$questionId] ?? null;
                if ($answer === null) {
                    continue;
                }
                $pairs[$questionId][] = [match ($answer['type']) {
                    'binary' => (float) $answer['probability'],
                    'score' => $answer['maxLevel'] > 0 ? (float) $answer['score'] / $answer['maxLevel'] : 0.0,
                    'choice' => $answer['choice'],
                }, $truth];
            }
        }

        $questions = [];
        foreach ($pairs as $questionId => $items) {
            $questions[$questionId] = is_string($items[0][1])
                ? self::choice($items)
                : self::probability($items, $threshold);
        }

        return ['purpose' => $purpose, 'version' => $version, 'labelled' => $rows->count(), 'threshold' => $threshold, 'questions' => $questions];
    }

    private static function probability(array $items, float $threshold): array
    {
        $n = count($items);
        $bands = [];
        foreach ($items as [$p, $truth]) {
            $band = min(9, (int) floor($p * 10));
            $bands[$band]['truths'][] = $truth ? 1 : 0;
            $bands[$band]['predictions'][] = $p;
        }
        ksort($bands);

        return [
            'kind' => 'probability',
            'n' => $n,
            'brier' => array_sum(array_map(fn ($i) => ($i[0] - ($i[1] ? 1 : 0)) ** 2, $items)) / $n,
            'baseRate' => count(array_filter($items, fn ($i) => $i[1])) / $n,
            'falsePositives' => count(array_filter($items, fn ($i) => $i[0] >= $threshold && ! $i[1])),
            'falseNegatives' => count(array_filter($items, fn ($i) => $i[0] < $threshold && $i[1])),
            'bands' => array_map(fn ($band, $data) => [
                'range' => sprintf('%.1f–%.1f', $band / 10, ($band + 1) / 10),
                'n' => count($data['truths']),
                'actualRate' => array_sum($data['truths']) / count($data['truths']),
                'meanPredicted' => array_sum($data['predictions']) / count($data['predictions']),
            ], array_keys($bands), $bands),
        ];
    }

    private static function choice(array $items): array
    {
        return [
            'kind' => 'choice',
            'n' => count($items),
            'hitRate' => count(array_filter($items, fn ($i) => $i[0] === $i[1])) / count($items),
        ];
    }
}
