<?php

namespace Tests\Support;

use Firebase\JWT\JWT;

/**
 * Generates a throwaway RSA keypair per test and signs/serves JWTs against it, so Apple/Google
 * identity-token tests exercise the real signature-verification code path (JwksVerifier) instead
 * of stubbing it out — a stubbed verifier would never have caught a real signature-check bug.
 */
trait FakesIdentityTokens
{
    private ?string $fakePrivateKeyPem = null;

    private function fakeKid(): string
    {
        return 'test-key-1';
    }

    private function fakePrivateKey(): string
    {
        if ($this->fakePrivateKeyPem !== null) {
            return $this->fakePrivateKeyPem;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $pem);

        return $this->fakePrivateKeyPem = $pem;
    }

    private function fakeJwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->fakePrivateKey()));

        return [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $this->fakeKid(),
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ]],
        ];
    }

    private function signIdToken(array $claims): string
    {
        return JWT::encode($claims, $this->fakePrivateKey(), 'RS256', $this->fakeKid());
    }
}
