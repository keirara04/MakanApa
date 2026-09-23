<?php

namespace App\Services\Judgment;

/**
 * Deterministic trimming: the same input always yields the same state (and so the same prompt
 * and state_hash). Keeps the first N items; cuts text on a word boundary with an ellipsis marker.
 */
final class StateTrimmer
{
    public static function text(?string $text, int $maxChars): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }
        $cut = mb_substr($text, 0, $maxChars);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $maxChars * 0.6) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut).'…';
    }

    /** @template T @param list<T>|null $items @return list<T> */
    public static function list(?array $items, int $maxItems): array
    {
        return array_values(array_slice(array_values($items ?? []), 0, $maxItems));
    }
}
