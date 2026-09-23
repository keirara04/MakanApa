<?php

namespace App\Services\Judgment\Definitions;

use App\Services\Judgment\JudgmentDefinition;
use App\Services\Judgment\Questions\ChoiceQuestion;
use App\Services\Judgment\StateTrimmer;
use App\Support\FoodTaxonomy;

/**
 * Maps a free-text craving (often Malay/Manglish) onto the FoodTaxonomy vocabulary — the model
 * only ever selects an existing concept or `none`; it never authors search text. Asked once:
 * a user is waiting (CravingResolver caches the result).
 */
final class CravingChoiceV1 extends JudgmentDefinition
{
    public const NONE = 'none';

    public function purpose(): string
    {
        return 'craving';
    }

    public function version(): int
    {
        return 1;
    }

    public function questions(): array
    {
        $options = [];
        foreach (FoodTaxonomy::CONCEPTS as $key => $concept) {
            $options[$key] = $concept['label'].' — e.g. '.implode(', ', $concept['aliases']);
        }
        $options[self::NONE] = 'Not a food craving, or nothing in this list fits.';

        return [
            new ChoiceQuestion(
                'concept',
                'Which food concept best matches what the user is craving in `evidence.craving`? '
                .'Pick the most specific concept that fits (a dish over its general category). '
                .'Pick `none` if nothing fits or it is not about food.',
                $options,
            ),
        ];
    }

    /** @param array{text: string, localHint?: array|null} $context */
    public function buildState(array $context): array
    {
        $hint = $context['localHint'] ?? null;

        return [
            'evidence' => ['craving' => StateTrimmer::text($context['text'], 200)],
            'context' => ['keyword_guess' => $hint['concept'] ?? null],
        ];
    }
}
