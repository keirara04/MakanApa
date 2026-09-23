<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The one place share URLs are built. The app only ever receives `shareUrl` from the API, so the
 * domain, slug rules or campaign parameters can change without shipping a new app build.
 */
final class ShareLinks
{
    /** Where a shared link came from — anything else is recorded as null. */
    public const REFS = ['share', 'nudge'];

    public static function place(int $restaurantId, string $name, string $ref = 'share'): string
    {
        return self::base().'/p/'.self::placeKey($restaurantId, $name).'?ref='.$ref;
    }

    /** "624-kfc-jalan-reko" — the id is authoritative, the slug is cosmetic. */
    public static function placeKey(int $restaurantId, string $name): string
    {
        $slug = Str::slug($name);

        return $slug === '' ? (string) $restaurantId : "{$restaurantId}-{$slug}";
    }

    private static function base(): string
    {
        $domain = (string) config('marketing.domain');

        return $domain === 'localhost' ? rtrim(url('/'), '/') : 'https://'.$domain;
    }
}
