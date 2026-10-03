<?php

namespace App\Support;

/**
 * Absolute URLs on the public marketing site. The same web routes also answer on the API and
 * admin hosts, so canonical links, share links and the sitemap are always built from the
 * configured marketing domain rather than whichever host served the request. Locally (no domain
 * configured) they follow the current request, so links stay clickable on `php artisan serve`.
 */
final class MarketingUrl
{
    public static function base(): string
    {
        $domain = (string) config('marketing.domain');

        return self::isLocal() ? rtrim(url('/'), '/') : 'https://'.$domain;
    }

    /** "/support" → "https://makanapa.example/support"; "" or "/" → the home page. */
    public static function to(string $path): string
    {
        $path = ltrim($path, '/');

        return self::base().($path === '' ? '/' : '/'.$path);
    }

    private static function isLocal(): bool
    {
        $domain = (string) config('marketing.domain');

        return $domain === '' || $domain === 'localhost';
    }
}
