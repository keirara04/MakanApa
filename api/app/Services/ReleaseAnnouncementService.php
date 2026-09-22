<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ReleaseAnnouncement;

/**
 * Single dispatch path shared by the artisan command and the Filament admin page, so "broadcast
 * a release announcement" only exists in one place.
 */
class ReleaseAnnouncementService
{
    /** @return int users considered (not all will actually receive a push — via() on the notification itself gates by preference/device tokens) */
    public function broadcast(string $version, string $message, ?string $appStoreUrl = null): int
    {
        $considered = 0;

        // chunkById (not chunk) — rows can be updated/deleted while a broadcast this size runs.
        User::query()->chunkById(200, function ($users) use ($version, $message, $appStoreUrl, &$considered) {
            foreach ($users as $user) {
                $user->notify(new ReleaseAnnouncement($version, $message, $appStoreUrl));
                $considered++;
            }
        });

        return $considered;
    }
}
