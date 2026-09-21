<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * Thrown for any identity-token rejection (bad signature, wrong audience/issuer, expired,
 * nonce mismatch). Deliberately one exception type with a `$reason` rather than a subclass per
 * failure mode — callers only ever need to log the reason and return a generic 401, never branch
 * on it, and a generic 401 avoids telling a client which specific check failed.
 */
class InvalidIdentityTokenException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Invalid identity token: {$reason}");
    }
}
