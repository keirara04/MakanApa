<?php

namespace App\Services\Judgment;

use App\Services\Judgment\Definitions\CravingChoiceV1;
use App\Services\Judgment\Definitions\HalalTriageV1;
use App\Services\Judgment\Definitions\NonHalalSecondOpinionV1;
use InvalidArgumentException;

/** Maps purpose + version back to its definition (calibration reads logged rows by version). */
final class DefinitionRegistry
{
    private const DEFINITIONS = [
        'halal_triage' => [1 => HalalTriageV1::class],
        'non_halal_second_opinion' => [1 => NonHalalSecondOpinionV1::class],
        'craving' => [1 => CravingChoiceV1::class],
    ];

    public static function get(string $purpose, int $version): JudgmentDefinition
    {
        $class = self::DEFINITIONS[$purpose][$version] ?? throw new InvalidArgumentException("Unknown judgment {$purpose} v{$version}.");

        return new $class;
    }

    public static function latestVersion(string $purpose): int
    {
        return max(array_keys(self::DEFINITIONS[$purpose] ?? throw new InvalidArgumentException("Unknown purpose {$purpose}.")));
    }
}
