<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * The browsers' push services. Anything else would have the server POST to an address a
     * client chose (and hang a send on it), so other hosts are refused.
     */
    private const PUSH_SERVICE_HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'];

    /** Browsers per account; the oldest goes when a new one subscribes. */
    private const MAX_PER_USER = 10;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $known = collect(self::PUSH_SERVICE_HOSTS)->contains(fn (string $service) => $host === $service || str_ends_with($host, '.'.$service));
                if (! $known) {
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
