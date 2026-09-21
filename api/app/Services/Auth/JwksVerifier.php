<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared signature-verification core for both Apple and Google identity tokens — both providers
 * publish rotating RSA keys at a JWKS endpoint and expect the same fetch/cache/verify shape, so
 * the provider-specific verifiers (AppleIdentityTokenVerifier, GoogleIdentityTokenVerifier) only
 * add their own iss/aud/nonce checks on top of what this class confirms: the signature is valid
 * and the token isn't expired.
 */
class JwksVerifier
{
    public function __construct(
        private readonly string $jwksUrl,
        private readonly string $cacheKey,
        private readonly int $cacheTtlSeconds = 21600,
    ) {}

    /**
     * @throws InvalidIdentityTokenException
     */
    public function decode(string $jwt): \stdClass
    {
        $kid = $this->peekKid($jwt);
        $keySet = $this->keySet(forceRefresh: false);

        // A `kid` the cached set doesn't recognize usually means the provider rotated keys since
        // we last fetched — refetch once before giving up, rather than caching a stale set for
        // the full TTL and rejecting otherwise-valid tokens.
        if ($kid === null || ! isset($keySet[$kid])) {
            $keySet = $this->keySet(forceRefresh: true);
        }

        try {
            return JWT::decode($jwt, $keySet);
        } catch (Throwable $e) {
            throw new InvalidIdentityTokenException('signature_or_claims: '.$e->getMessage());
        }
    }

    /**
     * @throws InvalidIdentityTokenException
     */
    private function peekKid(string $jwt): ?string
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new InvalidIdentityTokenException('malformed_token');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);

        return $header['kid'] ?? null;
    }

    /**
     * @return array<string, \Firebase\JWT\Key>
     */
    private function keySet(bool $forceRefresh): array
    {
        if ($forceRefresh) {
            Cache::forget($this->cacheKey);
        }

        try {
            $raw = Cache::remember($this->cacheKey, $this->cacheTtlSeconds, function () {
                return Http::timeout(5)->get($this->jwksUrl)->throw()->json();
            });
        } catch (Throwable $e) {
            throw new InvalidIdentityTokenException('jwks_fetch_failed: '.$e->getMessage());
        }

        return JWK::parseKeySet($raw);
    }
}
