<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppleLoginRequest;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\GoogleLoginRequest;
use App\Http\Requests\LinkAccountRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateMyAffiliationRequest;
use App\Models\Area;
use App\Models\PendingProviderLink;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use App\Services\Auth\AppleIdentityTokenVerifier;
use App\Services\Auth\AppleTokenExchangeService;
use App\Services\Auth\GoogleIdentityTokenVerifier;
use App\Services\Auth\InvalidIdentityTokenException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    /**
     * Open self-service signup — no invite/beta code required (the app dropped its closed-beta
     * gate; admin-provisioned accounts via Admin\UserController::store() keep working alongside
     * this). Issues a token immediately, same as login(), rather than requiring a separate
     * sign-in step right after registering.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'status' => 'active',
        ]);

        $token = $user->createToken($data['deviceLabel'], expiresAt: now()->addDays(90));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->presentUser($user),
        ], 201);
    }

    /**
     * Continue-with-Apple. `sub` (not email) is the identity — Apple emails can be private
     * relay addresses, and the same rule applies here as in google(): an email match against
     * an existing account never auto-links, it only earns a linking offer.
     */
    public function apple(AppleLoginRequest $request, AppleIdentityTokenVerifier $verifier, AppleTokenExchangeService $exchange): JsonResponse
    {
        $data = $request->validated();

        try {
            $identity = $verifier->verify($data['identityToken'], $data['nonce']);
        } catch (InvalidIdentityTokenException) {
            return response()->json(['message' => 'Invalid Apple identity token.'], 401);
        }

        // Single-use grant — exchanged now regardless of whether this resolves to an immediate
        // login or a pending link, since the code can't be redeemed a second time later.
        $refreshToken = $exchange->exchange($data['authorizationCode']);

        return $this->resolveSocialLogin(
            provider: 'apple',
            sub: $identity->sub,
            email: $identity->email,
            name: $data['fullName'] ?? null,
            deviceLabel: $data['deviceLabel'],
            providerRefreshToken: $refreshToken,
        );
    }

    /**
     * Continue-with-Google. Same sub-first, no-auto-link shape as apple() — see that method's
     * doc comment.
     */
    public function google(GoogleLoginRequest $request, GoogleIdentityTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validated();

        try {
            $identity = $verifier->verify($data['idToken']);
        } catch (InvalidIdentityTokenException) {
            return response()->json(['message' => 'Invalid Google identity token.'], 401);
        }

        return $this->resolveSocialLogin(
            provider: 'google',
            sub: $identity->sub,
            email: $identity->email,
            name: null,
            deviceLabel: $data['deviceLabel'],
        );
    }

    /**
     * Completes a pending social link. The client supplies only a password and the opaque
     * linkToken — never a provider identity — because the provider_sub was already verified
     * server-side at the moment /auth/apple or /auth/google issued this token (see
     * resolveSocialLogin()). A wrong password does not consume the token, so retries stay
     * possible until it expires or the per-token throttle catches repeated guessing.
     */
    public function link(LinkAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tokenHash = hash('sha256', $data['linkToken']);

        $pending = PendingProviderLink::where('token_hash', $tokenHash)->first();

        if (! $pending || $pending->isConsumed() || $pending->isExpired()) {
            return response()->json(['message' => 'This link request is no longer valid.'], 401);
        }

        $user = $pending->user;

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        DB::transaction(function () use ($pending, $user) {
            $user->forceFill([
                $pending->provider.'_sub' => $pending->provider_sub,
                ...($pending->provider === 'apple' && $pending->apple_refresh_token
                    ? ['apple_refresh_token' => $pending->apple_refresh_token]
                    : []),
            ])->save();

            $pending->forceFill(['consumed_at' => now()])->save();
        });

        $token = $user->createToken($data['deviceLabel'], expiresAt: now()->addDays(90));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->presentUser($user->fresh()),
        ]);
    }

    /**
     * Shared lookup for both social providers: `$provider`_sub match logs in; no match with no
     * colliding email creates a new account; no match WITH a colliding email issues a pending
     * link instead of merging — the caller (apple()/google()) already verified `$sub` against
     * the provider's own signature, so this method is the only writer of `{provider}_sub` into
     * either a user row or a pending_provider_links row.
     */
    private function resolveSocialLogin(
        string $provider,
        string $sub,
        ?string $email,
        ?string $name,
        string $deviceLabel,
        ?string $providerRefreshToken = null,
    ): JsonResponse {
        $subColumn = $provider.'_sub';

        $user = User::where($subColumn, $sub)->first();

        if ($user) {
            if ($provider === 'apple' && $providerRefreshToken) {
                $user->forceFill(['apple_refresh_token' => $providerRefreshToken])->save();
            }

            return $this->issueSession($user, $deviceLabel);
        }

        if ($email) {
            $existing = User::where('email', $email)->first();

            if ($existing) {
                $rawToken = Str::random(64);

                PendingProviderLink::create([
                    'token_hash' => hash('sha256', $rawToken),
                    'user_id' => $existing->id,
                    'provider' => $provider,
                    'provider_sub' => $sub,
                    'apple_refresh_token' => $provider === 'apple' ? $providerRefreshToken : null,
                    'expires_at' => now()->addMinutes(10),
                ]);

                return response()->json([
                    'needsLinking' => true,
                    'linkToken' => $rawToken,
                    'email' => $email,
                ]);
            }
        }

        try {
            // Wrapped in its own transaction (a savepoint, since a request is already inside
            // RefreshDatabase's/Postgres's outer transaction) so a unique-constraint violation
            // below only rolls back this insert — Postgres otherwise poisons the whole
            // transaction on error, which would make the fallback lookup in the catch block
            // fail too instead of finding the winner's row.
            $user = DB::transaction(function () use ($name, $email, $subColumn, $sub, $provider, $providerRefreshToken) {
                return User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => null,
                    'role' => 'user',
                    'status' => 'active',
                    $subColumn => $sub,
                    'apple_refresh_token' => $provider === 'apple' ? $providerRefreshToken : null,
                ]);
            });
        } catch (QueryException $e) {
            // Two simultaneous first-time logins with the same sub race past the lookup above —
            // the unique constraint on {provider}_sub catches the loser here, who then just
            // re-reads the winner's row instead of surfacing a 500.
            $user = User::where($subColumn, $sub)->first();

            if (! $user) {
                throw $e;
            }
        }

        return $this->issueSession($user, $deviceLabel);
    }

    private function issueSession(User $user, string $deviceLabel): JsonResponse
    {
        $token = $user->createToken($deviceLabel, expiresAt: now()->addDays(90));

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

    /**
     * Self-service account deletion — required by App Store review for any app that supports
     * account creation. Revokes the Apple grant first (if any), then removes every Sanctum
     * token before deleting the row, rather than relying solely on cascading FKs to clean up
     * everything, since token cleanup isn't itself a foreign key (Sanctum uses a polymorphic
     * `tokenable` column, not a constrained one).
     */
    public function destroy(DeleteAccountRequest $request, AppleTokenExchangeService $exchange): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Only password accounts need to prove intent this way — a social-only account
        // (password === null) already proved a recent sign-in to obtain this Sanctum token.
        if ($user->password !== null) {
            if (empty($data['password']) || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['message' => 'Incorrect password.'], 401);
            }
        }

        if ($user->apple_refresh_token) {
            $exchange->revoke($user->apple_refresh_token);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['deleted' => true]);
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
        $area = isset($data['area']) ? Area::where('short_name', $data['area'])->first() : null;

        UserAffiliation::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'type' => $university ? 'university' : ($area ? 'area' : 'public'),
                'university_id' => $university?->id,
                'area_id' => $area?->id,
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
        $user->loadMissing('affiliation.university', 'affiliation.area');

        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'affiliationType' => $user->affiliation?->type,
            'university' => $user->universityShortName(),
            'area' => $user->areaShortName(),
            'affiliationVerificationStatus' => $user->affiliation?->verification_status,
        ];
    }
}
