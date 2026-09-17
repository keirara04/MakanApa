<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Models\User;
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
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'role', 'status', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'createdAt' => $user->created_at?->toIso8601String(),
            ]);

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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'createdAt' => $user->created_at?->toIso8601String(),
            ],
            'temporaryPassword' => $temporaryPassword,
        ], 201);
    }

    public function revoke(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot revoke your own access.');

        $user->update(['status' => 'revoked']);
        $user->tokens()->delete();

        return response()->json(['revoked' => true]);
    }
}
