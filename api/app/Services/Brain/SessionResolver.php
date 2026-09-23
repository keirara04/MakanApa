<?php

namespace App\Services\Brain;

use App\Models\Decision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * A "sitting": decisions less than N minutes apart share a session id, derived server-side so
 * the client never has to manage one. One indexed read on the latest decision.
 */
class SessionResolver
{
    public function resolve(?TasteOwner $owner): string
    {
        if ($owner === null) {
            return (string) Str::uuid();
        }

        $latest = $owner->scope(Decision::query())
            ->whereNotNull('session_id')
            ->orderByDesc('id')
            ->first(['session_id', 'created_at']);

        $gap = (int) Config::get('brain.pulse.session_gap_minutes', 20);

        return $latest && $latest->created_at && Carbon::parse($latest->created_at)->gt(now()->subMinutes($gap))
            ? $latest->session_id
            : (string) Str::uuid();
    }
}
