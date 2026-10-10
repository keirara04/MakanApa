<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where the website sends people to use MakanApa: Android visitors go to the web app once it's
 * live (WEB_APP_LIVE), everyone else keeps the App Store. Bots never get the Android variant,
 * so search engines (Googlebot's phone crawler says "Android") index the App Store page.
 */
final class WebAppEntry
{
    public static function live(): bool
    {
        return (bool) config('marketing.web_app_live');
    }

    public static function forAndroid(?Request $request = null): bool
    {
        $userAgent = (string) ($request ?? request())->userAgent();

        return self::live() && str_contains($userAgent, 'Android') && ! BotUserAgent::matches($userAgent);
    }

    /** iPhone and iPad keep the App Store as their only way in. */
    public static function forApple(?Request $request = null): bool
    {
        return (bool) preg_match('/iPhone|iPad|iPod/', (string) ($request ?? request())->userAgent());
    }
}
