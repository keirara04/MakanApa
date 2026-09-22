<?php

namespace App\Support;

/**
 * The only two timezones admins are offered for the Filament panel's display-only clock — not a
 * general timezone list. Storage/PHP date functions stay on config('app.timezone') (UTC)
 * regardless of this; see FilamentTimezone::set() in AdminPanelProvider.
 */
final class AdminTimezone
{
    public const KUALA_LUMPUR = 'Asia/Kuala_Lumpur';

    public const SEOUL = 'Asia/Seoul';

    public const DEFAULT = self::KUALA_LUMPUR;

    public const OPTIONS = [
        self::KUALA_LUMPUR => 'Kuala Lumpur (UTC+8)',
        self::SEOUL => 'Seoul (UTC+9)',
    ];
}
