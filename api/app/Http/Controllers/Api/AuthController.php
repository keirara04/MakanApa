<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateMyAffiliationRequest;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Manual credential check (not Auth::attempt) matching Sanctum's documented mobile-token
     * flow: verify email/password, issue a token for the named device. A single generic
     * "invalid credentials" response covers both "no such account" and "wrong password" so
     * neither leaks which one it was.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->isActive()) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $user->createToken($data['deviceLabel'], expiresAt: now()->addDays(90));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->presentUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['loggedOut' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->presentUser($request->user())]);
    }

    /**
     * Self-service counterpart to admin `UserController::store()`'s affiliation write. Always
     * overwrites every verification column together (not just `type`/`university_id`) so a
     * user changing away from an admin-verified affiliation can't keep riding on that old
     * `verified` status — every self-picked value, including Public, lands as explicitly
     * self_reported with no verified_at, distinct from "no affiliation row yet".
     */
    public function updateAffiliation(UpdateMyAffiliationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $university = isset($data['university']) ? University::where('short_name', $data['university'])->first() : null;

        UserAffiliation::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'type' => $university ? 'university' : 'public',
                'university_id' => $university?->id,
                'verification_status' => 'self_reported',
                'verification_method' => 'self_reported',
                'verified_at' => null,
            ]
        );

        $request->user()->unsetRelation('affiliation');

        return response()->json(['user' => $this->presentUser($request->user())]);
    }

    private function presentUser(User $user): array
    {
        $user->loadMissing('affiliation.university');

        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'affiliationType' => $user->affiliation?->type,
            'university' => $user->universityShortName(),
            'affiliationVerificationStatus' => $user->affiliation?->verification_status,
        ];
    }
}
