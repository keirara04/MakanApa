<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['subscribed' => false]);
    }
}
