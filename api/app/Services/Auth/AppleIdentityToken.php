<?php

namespace App\Services\Auth;

/**
 * Verified claims pulled off an Apple identity token — a value object, not the raw token, so
 * nothing downstream can accidentally trust unverified claims. `sub` is the identity; `email`
 * is a profile hint only (may be a private relay address, may be absent on returning sign-ins).
 */
final readonly class AppleIdentityToken
{
    public function __construct(
        public string $sub,
        public ?string $email,
    ) {}
}
