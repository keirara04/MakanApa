<?php

namespace App\Support\Halal;

/** Lifecycle of one restaurant_halal_verifications row. Only `Active` feeds the snapshot. */
enum HalalVerificationState: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Disputed = 'disputed';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
