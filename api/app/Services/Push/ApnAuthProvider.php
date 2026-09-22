<?php

namespace App\Services\Push;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Pushok\AuthProviderInterface;
use Pushok\Request;

/**
 * Drop-in replacement for edamov/pushok's own Pushok\AuthProvider\Token (v0.18/0.19, the range
 * laravel-notification-channels/apn allows). That vendor class hardcodes the JWT `alg` header to
 * ES512 — but Apple's APNs auth keys (the .p8 downloaded from the Developer portal) are P-256
 * keys, and APNs requires `alg: ES256` for them. Signing with ES512 against a P-256 key makes
 * every single push fail with "InvalidProviderToken" no matter how correct key_id/team_id/the
 * key file are. See Apple's own spec: https://developer.apple.com/documentation/usernotifications/establishing-a-token-based-connection-to-apns
 * Bound over the vendor Token class in AppServiceProvider.
 */
class ApnAuthProvider implements AuthProviderInterface
{
    private readonly string $token;

    public function __construct(
        private readonly string $keyId,
        private readonly string $teamId,
        private readonly string $appBundleId,
        string $privateKeyPath,
        ?string $privateKeySecret = null,
    ) {
        $this->token = $this->generate($privateKeyPath, $privateKeySecret);
    }

    public function authenticateClient(Request $request): void
    {
        $request->addHeaders([
            'apns-topic' => $this->generateApnsTopic($request->getHeaders()['apns-push-type']),
            'Authorization' => 'bearer '.$this->token,
        ]);
    }

    private function generateApnsTopic(string $pushType): string
    {
        return match ($pushType) {
            'voip' => $this->appBundleId.'.voip',
            'liveactivity' => $this->appBundleId.'.push-type.liveactivity',
            'complication' => $this->appBundleId.'.complication',
            'fileprovider' => $this->appBundleId.'.pushkit.fileprovider',
            default => $this->appBundleId,
        };
    }

    private function generate(string $privateKeyPath, ?string $privateKeySecret): string
    {
        $key = JWKFactory::createFromKeyFile($privateKeyPath, $privateKeySecret, [
            'kid' => $this->keyId,
            'alg' => 'ES256',
            'use' => 'sig',
        ]);

        $payload = json_encode([
            'iss' => $this->teamId,
            'iat' => time(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $jws = (new JWSBuilder(new AlgorithmManager([new ES256])))
            ->create()
            ->withPayload($payload)
            ->addSignature($key, ['alg' => 'ES256', 'kid' => $this->keyId])
            ->build();

        return (new CompactSerializer)->serialize($jws);
    }
}
