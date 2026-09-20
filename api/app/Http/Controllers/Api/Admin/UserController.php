<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deliberately minimal for this beta phase: the seeded account is the only superadmin, so
 * there is no role-update endpoint and store() never accepts a client-supplied role or
 * password — every account created here is role=user with a server-generated temp password.
 * A promote/demote flow (with last-superadmin protection) is deferred until multiple admins
 * are actually needed.
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('affiliation.university')
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'role', 'status', 'created_at'])
            ->map(fn (User $user) => $this->presentUser($user));

        return response()->json(['users' => $users]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $temporaryPassword = Str::password(16);

        $user = User::create([
            'name' => $data['email'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => 'user',
            'status' => 'active',
        ]);

        $university = isset($data['university']) ? University::where('short_name', $data['university'])->first() : null;

        UserAffiliation::create([
            'user_id' => $user->id,
            'type' => $university ? 'university' : 'public',
            'university_id' => $university?->id,
            'verification_status' => 'verified',
            'verification_method' => 'admin_created',
            'verified_at' => now(),
        ]);

        $user->load('affiliation.university');

        return response()->json([
            'user' => $this->presentUser($user),
            'temporaryPassword' => $temporaryPassword,
        ], 201);
    }

    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'createdAt' => $user->created_at?->toIso8601String(),
            'affiliationType' => $user->affiliation?->type,
            'university' => $user->universityShortName(),
        ];
    }

    public function revoke(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot revoke your own access.');

        $user->update(['status' => 'revoked']);
        $user->tokens()->delete();

        return response()->json(['revoked' => true]);
    }
}
