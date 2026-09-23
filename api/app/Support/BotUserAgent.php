<?php

namespace App\Support;

/** Link-preview fetchers and scripts shouldn't inflate marketing or share funnels. */
final class BotUserAgent
{
    private const PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless/i';

    public static function matches(?string $userAgent): bool
    {
        return (bool) preg_match(self::PATTERN, (string) $userAgent);
    }
}
