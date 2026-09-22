<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimDeviceTokenRequest;
use App\Http\Requests\RegisterDeviceTokenRequest;
use App\Http\Requests\UnclaimDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Services\DeviceTokenUpsertService;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly DeviceTokenUpsertService $upsertService,
    ) {}

    /** Public — called on every app launch/APNs (re)registration, authenticated or not. Never touches user_id. */
    public function register(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->upsertService->upsert($data['installationId'], $data['token'], $data['environment']);

        return response()->json(['ok' => true]);
    }

    /** Authed — called right after login. Intentional ownership overwrite if a different user previously claimed this installation (e.g. a shared/resold device). */
    public function claim(ClaimDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $device = $this->upsertService->upsert($data['installationId'], $data['token'], $data['environment']);
        $device->update(['user_id' => $request->user()->id]);

        return response()->json(['ok' => true]);
    }

    /** Authed — called on logout. Unclaims (user_id = null) rather than deleting, so the installation stays eligible for anonymous/transactional-safe pushes. Scoped to the caller's own claim so a stale session can't detach another account's device. */
    public function unclaim(UnclaimDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        DeviceToken::query()
            ->where('installation_id', $data['installationId'])
            ->where('environment', $data['environment'])
            ->where('user_id', $request->user()->id)
            ->update(['user_id' => null]);

        return response()->json(['ok' => true]);
    }
}
