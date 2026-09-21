<?php

namespace App\Services\Auth;

/**
 * Verified claims pulled off a Google identity token. `sub` is the identity; `email` is a
 * profile hint only — never used as the lookup key (see AuthController::google()).
 */
final readonly class GoogleIdentityToken
{
    public function __construct(
        public string $sub,
        public ?string $email,
    ) {}
}
