<?php

namespace App\Support\Halal;

/**
 * Conservative, explainable non-halal detector. A false positive hides a restaurant from every
 * Muslim user, so it errs hard toward NOT classifying:
 *  - it can only ever say "likely non-halal" — never that anything is halal;
 *  - matching is on whole words/phrases after normalization, never substrings
 *    ("bar" must not hit "Barbeque" or "Nasi Kandar");
 *  - weak terms (wine, beer, pub) are recorded for the admin but never classify on their own.
 */
class HalalHeuristic
{
    private const STRONG_TERMS = [
        'pork', 'babi', 'bak kut teh', 'char siu', 'char siew', 'siew yoke', 'siu yuk', 'lard',
        'non halal', 'brewery',
    ];

    private const WEAK_TERMS = ['wine', 'beer', 'pub', 'bar', 'tavern', 'liquor'];

    private const STRONG_GOOGLE_TYPES = ['bar', 'night_club'];

    private const TEXT_FIELDS = ['name', 'signature_dish', 'food_category'];

    public static function evaluate(array $restaurant): HalalHeuristicResult
    {
        $matches = [];

        foreach (self::TEXT_FIELDS as $field) {
            $text = self::normalize((string) ($restaurant[$field] ?? ''));
            if ($text === '') {
                continue;
            }

            foreach (self::STRONG_TERMS as $term) {
                if (self::containsPhrase($text, $term)) {
                    $matches[] = ['field' => $field, 'term' => $term, 'strength' => 'strong'];
                }
            }
            foreach (self::WEAK_TERMS as $term) {
                if (self::containsPhrase($text, $term)) {
                    $matches[] = ['field' => $field, 'term' => $term, 'strength' => 'weak'];
                }
            }
        }

        foreach (array_intersect($restaurant['google_types'] ?? [], self::STRONG_GOOGLE_TYPES) as $type) {
            $matches[] = ['field' => 'google_types', 'term' => $type, 'strength' => 'strong'];
        }

        $likely = collect($matches)->contains(fn (array $m) => $m['strength'] === 'strong');

        return new HalalHeuristicResult($likely, $matches);
    }

    /** Lowercase, strip accents, turn every non-alphanumeric run (incl. "-", "&") into one space. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $ascii !== false ? $ascii : $text;
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim($text);
    }

    private static function containsPhrase(string $normalizedText, string $term): bool
    {
        return preg_match('/(?<![a-z0-9])'.preg_quote($term, '/').'(?![a-z0-9])/', $normalizedText) === 1;
    }
}
