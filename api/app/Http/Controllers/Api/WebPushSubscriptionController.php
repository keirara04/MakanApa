<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\PushSubscription;

/**
 * The web app's push subscriptions (the browser's PushSubscription), the Web Push twin of the
 * iOS device tokens. Every web session has a user (guest or not), so subscriptions belong to the
 * account; a browser that switches account moves its subscription with it.
 */
class WebPushSubscriptionController extends Controller
{
    /** The VAPID public key the browser needs to subscribe. */
    public function key(): JsonResponse
    {
        return response()->json(['publicKey' => config('webpush.vapid.public_key')]);
    }

    /** Browsers per account; the oldest goes when a new one subscribes. */
    private const MAX_PER_USER = 10;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail) {
                // Browsers' push services only (config/webpush.php): anything else would have the
                // server POST to an address a client chose. Logged, so a browser with its own
                // push service shows up the first time someone tries it.
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $known = collect(config('webpush.push_service_hosts'))->contains(fn (string $service) => $host === $service || str_ends_with($host, '.'.$service));
                if (! $known) {
                    Log::info('web_push.unknown_host', ['host' => $host]);
                    $fail('This browser’s push service isn’t supported.');
                }
            }],
            // Sending encrypts to these keys: a key that isn't one would fail every send to this account.
            'keys.p256dh' => ['required', 'string', 'max:255', $this->decodesTo(65)],
            'keys.auth' => ['required', 'string', 'max:255', $this->decodesTo(16)],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $user = $request->user();
        $user->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        $user->pushSubscriptions()->latest('updated_at')->latest('id')->skip(self::MAX_PER_USER)->take(PHP_INT_MAX)->get()->each->delete();

        return response()->json(['subscribed' => true]);
    }

    /** A base64url value of exactly this many bytes (an uncompressed P-256 key is 65, the auth secret 16). */
    private function decodesTo(int $bytes): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($bytes) {
            $decoded = base64_decode(strtr((string) $value, '-_', '+/'), true);
            if ($decoded === false || strlen($decoded) !== $bytes || ($bytes === 65 && $decoded[0] !== "\x04")) {
                $fail('This browser’s push keys aren’t valid.');
            }
        };
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH]]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['subscribed' => false]);
    }
}
