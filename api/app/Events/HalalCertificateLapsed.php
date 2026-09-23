<?php

namespace App\Events;

use App\Models\RestaurantHalalCertificate;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after commit when a certificate is expired or revoked. */
class HalalCertificateLapsed
{
    use Dispatchable;

    public function __construct(
        public readonly RestaurantHalalCertificate $certificate,
        public readonly string $reason, // expired | revoked
    ) {}
}
