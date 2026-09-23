<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Pre-publication check for community posts — the only gate before a post goes live (see
 * config/moderation.php). Intentionally simple: a whole-word blocklist plus a link ban.
 * Anything subtler is left to reports + admin review rather than guessed at here.
 */
final class CommunityContentFilter
{
    /** @return string|null a user-facing rejection reason, or null when the body is fine */
    public static function rejectionReason(string $body): ?string
    {
        if (self::containsLink($body)) {
            return "Links aren't allowed in community posts.";
        }

        if (self::containsBlockedWord($body)) {
            return 'Please keep it friendly — that wording isn\'t allowed.';
        }

        return null;
    }

    private static function containsLink(string $body): bool
    {
        return (bool) preg_match('~(https?://|www\.)\S+|\b[a-z0-9-]+\.(com|net|org|my|io|co|app|xyz|link|ly)\b~i', $body);
    }

    private static function containsBlockedWord(string $body): bool
    {
        $normalized = self::normalize($body);

        foreach (Config::get('moderation.community_posts.blocked_words', []) as $word) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote(self::normalize($word), '/').'(?![\p{L}\p{N}])/u';
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /** Lowercase, undo the most common character swaps, and collapse 3+ repeated letters ("fuuuck"). */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = strtr($text, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i']);

        return preg_replace('/(\p{L})\1{2,}/u', '$1', $text) ?? $text;
    }
}
