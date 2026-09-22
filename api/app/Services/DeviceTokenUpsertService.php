<?php

namespace App\Services;

use App\Models\DeviceToken;

/**
 * Single upsert path shared by DeviceTokenController's register() and claim(), so the two
 * requests can never produce divergent records on a race (e.g. an APNs re-registration landing
 * moments before/after a login's claim call).
 */
class DeviceTokenUpsertService
{
    public function upsert(string $installationId, string $token, string $environment): DeviceToken
    {
        return DeviceToken::updateOrCreate(
            [
                'installation_id' => $installationId,
                'environment' => $environment,
            ],
            [
                // Lowercased so equivalent hex tokens (e.g. differing only in case) always
                // resolve to the same row.
                'token' => strtolower($token),
                'platform' => 'ios',
                'last_seen_at' => now(),
                'invalidated_at' => null,
                // user_id is deliberately absent here — an already-claimed installation must
                // stay claimed across token rotation/re-registration. Only claim()/unclaim()
                // change ownership.
            ],
        );
    }
}
