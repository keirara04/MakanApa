<?php

namespace App\Services\Auth;

class AppleIdentityTokenVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const ISSUER = 'https://appleid.apple.com';

    public function __construct(private readonly JwksVerifier $jwks = new JwksVerifier(self::JWKS_URL, 'jwks:apple')) {}

    /**
     * @throws InvalidIdentityTokenException
     */
    public function verify(string $identityToken, string $rawNonce): AppleIdentityToken
    {
        $claims = $this->jwks->decode($identityToken);

        if (($claims->iss ?? null) !== self::ISSUER) {
            throw new InvalidIdentityTokenException('unexpected_issuer');
        }

        if (($claims->aud ?? null) !== config('services.apple.client_id')) {
            throw new InvalidIdentityTokenException('unexpected_audience');
        }

        // Apple embeds the exact hash it was given at authorization request time — not a fresh
        // hash of anything in the token — so the check is a direct string comparison against
        // recomputing the hash of the raw nonce the client is presenting now. This is what
        // closes off replaying a captured identity token against a different login attempt.
        $expectedNonce = hash('sha256', $rawNonce);
        if (! hash_equals($expectedNonce, (string) ($claims->nonce ?? ''))) {
            throw new InvalidIdentityTokenException('nonce_mismatch');
        }

        return new AppleIdentityToken(
            sub: (string) $claims->sub,
            email: $claims->email ?? null,
        );
    }
}
