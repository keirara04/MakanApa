<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Log;

class GoogleIdentityTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    // Google issues tokens under either form depending on account type — both are accepted
    // issuers per Google's own token-verification docs.
    private const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(private readonly JwksVerifier $jwks = new JwksVerifier(self::JWKS_URL, 'jwks:google')) {}

    /**
     * @throws InvalidIdentityTokenException
     */
    public function verify(string $idToken): GoogleIdentityToken
    {
        $claims = $this->jwks->decode($idToken);

        if (! in_array($claims->iss ?? null, self::VALID_ISSUERS, true)) {
            Log::warning('google_identity_token.unexpected_issuer', ['iss' => $claims->iss ?? null]);

            throw new InvalidIdentityTokenException('unexpected_issuer');
        }

        // Checked against the Web/server OAuth client ID, not an iOS client ID — see
        // config/services.php's `google.server_client_id` doc comment for why.
        $expectedAudience = config('services.google.server_client_id');
        if (($claims->aud ?? null) !== $expectedAudience) {
            // Deliberately logs both sides, not just a boolean — this is the only way to tell
            // "GOOGLE_SERVER_CLIENT_ID is unset/wrong on this deploy" apart from "someone sent
            // a token for a different app" after the fact, without a debugger attached.
            Log::warning('google_identity_token.unexpected_audience', [
                'token_aud' => $claims->aud ?? null,
                'expected_aud' => $expectedAudience,
            ]);

            throw new InvalidIdentityTokenException('unexpected_audience');
        }

        return new GoogleIdentityToken(
            sub: (string) $claims->sub,
            email: $claims->email ?? null,
        );
    }
}
