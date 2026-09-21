<?php

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Exchanges the one-time `authorizationCode` iOS gets from ASAuthorizationAppleIDCredential for
 * a refresh token, so account deletion can later call Apple's revocation endpoint with something
 * — Apple requires apps that support account creation to revoke associated provider tokens on
 * deletion, not just delete the local row. Best-effort: a failure here shouldn't block sign-in,
 * since the refresh token only matters much later, at deletion time.
 */
class AppleTokenExchangeService
{
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    /**
     * @return string|null the refresh token, or null if the exchange couldn't complete
     */
    public function exchange(string $authorizationCode): ?string
    {
        try {
            $response = Http::asForm()->timeout(5)->post(self::TOKEN_URL, [
                'client_id' => config('services.apple.client_id'),
                'client_secret' => $this->clientSecret(),
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
            ])->throw();

            return $response->json('refresh_token');
        } catch (Throwable $e) {
            Log::warning('[AppleTokenExchangeService] authorization code exchange failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Called on account deletion — Apple requires apps that support Sign in with Apple to revoke
     * the associated token when the account is deleted, not just delete the local row.
     * Best-effort: a failed revoke shouldn't block the deletion itself from completing.
     */
    public function revoke(string $refreshToken): void
    {
        try {
            Http::asForm()->timeout(5)->post(self::REVOKE_URL, [
                'client_id' => config('services.apple.client_id'),
                'client_secret' => $this->clientSecret(),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ])->throw();
        } catch (Throwable $e) {
            Log::warning('[AppleTokenExchangeService] refresh token revocation failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Apple's token endpoint requires a server-to-server "client secret" that is itself a
     * short-lived ES256 JWT signed with a private key generated in the Apple Developer portal —
     * not a static shared secret.
     */
    private function clientSecret(): string
    {
        $now = time();

        return JWT::encode(
            [
                'iss' => config('services.apple.team_id'),
                'iat' => $now,
                'exp' => $now + 300,
                'aud' => 'https://appleid.apple.com',
                'sub' => config('services.apple.client_id'),
            ],
            $this->privateKeyPem(),
            'ES256',
            config('services.apple.key_id'),
        );
    }

    /**
     * openssl (via firebase/php-jwt) requires PEM framing to parse an EC key. Apple's downloaded
     * `.p8` file already has it, but copying just the key body into an env var (easy to do by
     * accident) strips it — so this reconstructs the framing when it's missing rather than
     * failing on an otherwise-valid key.
     */
    private function privateKeyPem(): string
    {
        $raw = trim((string) config('services.apple.private_key'));

        if (str_contains($raw, '-----BEGIN')) {
            return str_replace('\n', "\n", $raw);
        }

        return "-----BEGIN PRIVATE KEY-----\n{$raw}\n-----END PRIVATE KEY-----";
    }
}
