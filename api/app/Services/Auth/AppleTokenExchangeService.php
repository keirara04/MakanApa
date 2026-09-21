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
            str_replace('\n', "\n", (string) config('services.apple.private_key')),
            'ES256',
            config('services.apple.key_id'),
        );
    }
}
