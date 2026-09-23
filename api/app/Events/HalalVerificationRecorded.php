<?php

namespace App\Events;

use App\Models\RestaurantHalalVerification;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after commit whenever a new verification becomes active (and optionally replaces one). */
class HalalVerificationRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly RestaurantHalalVerification $verification,
        public readonly ?RestaurantHalalVerification $superseded,
    ) {}
}
