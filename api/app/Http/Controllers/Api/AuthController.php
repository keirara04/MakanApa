<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Requests\AcceptTermsRequest;
use App\Http\Requests\AppleLoginRequest;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\GoogleLoginRequest;
use App\Http\Requests\GuestLoginRequest;
use App\Http\Requests\LinkAccountRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateMyAffiliationRequest;
use App\Http\Requests\UpdateMyProfileRequest;
use App\Models\Area;
use App\Models\PendingProviderLink;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use App\Services\Auth\AppleIdentityTokenVerifier;
use App\Services\Auth\AppleTokenExchangeService;
use App\Services\Auth\GoogleIdentityTokenVerifier;
use App\Services\Auth\InvalidIdentityTokenException;
use App\Services\UserAccountDeletionService;
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

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Only after the password checks out, so this never reveals that an account exists.
        if (! $user->isActive()) {
            return EnsureActiveUser::suspendedResponse();
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
     * sign-in step right after registering. Sent with a guest's bearer token, it upgrades that
     * guest row in place instead of creating a second account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $identity = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'signup_source' => $data['signupSource'] ?? null,
        ];

        $guest = $this->guestFromBearerToken();

        $user = DB::transaction(fn () => $guest
            ? $this->upgradeGuest($guest, $identity)
            : User::create([...$identity, 'role' => 'user', 'status' => 'active']));

        $token = $user->createToken($data['deviceLabel'], expiresAt: now()->addDays(90));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->presentUser($user),
        ], 201);
    }

    /**
     * Anonymous account so the app's suggestions work without registering (App Review
     * 5.1.1(v)) — no name, email or password. Account-based routes refuse it via the
     * `registered` middleware; signing up/in later with this token attached upgrades the same
     * row (register()/resolveSocialLogin()), so history and saves carry over.
     */
    public function guest(GuestLoginRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => null,
            'email' => null,
            'password' => null,
            'role' => 'user',
            'status' => 'active',
            'is_guest' => true,
        ]);

        $token = $user->createToken($request->validated('deviceLabel'), expiresAt: now()->addDays(90));

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
            signupSource: $data['signupSource'] ?? null,
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
            signupSource: $data['signupSource'] ?? null,
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

        if (! $user->isActive()) {
            return EnsureActiveUser::suspendedResponse();
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
        ?string $signupSource = null,
    ): JsonResponse {
        $subColumn = $provider.'_sub';

        // withTrashed(): a soft-deleted row still occupies the unique email/{provider}_sub
        // index in Postgres, so a plain (non-trashed) lookup here would miss it and fall
        // through to User::create() below — which then dies on the unique-constraint
        // violation instead of a clean response. Must see trashed rows to react to them.
        $user = User::withTrashed()->where($subColumn, $sub)->first();

        if ($user && $user->trashed()) {
            return response()->json(['message' => 'This account was deleted.'], 410);
        }

        if ($user) {
            // The provider already proved who this is, so saying "suspended" leaks nothing —
            // and without this check a suspended account could just sign back in.
            if (! $user->isActive()) {
                return EnsureActiveUser::suspendedResponse();
            }

            if ($provider === 'apple' && $providerRefreshToken) {
                $user->forceFill(['apple_refresh_token' => $providerRefreshToken])->save();
            }

            return $this->issueSession($user, $deviceLabel);
        }

        if ($email) {
            $existing = User::withTrashed()->where('email', $email)->first();

            if ($existing && $existing->trashed()) {
                return response()->json(['message' => 'This account was deleted.'], 410);
            }

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
            $identity = [
                'name' => $name,
                'email' => $email,
                'password' => null,
                $subColumn => $sub,
                'apple_refresh_token' => $provider === 'apple' ? $providerRefreshToken : null,
                'signup_source' => $signupSource,
            ];
            $guest = $this->guestFromBearerToken();

            $user = DB::transaction(function () use ($identity, $guest) {
                return $guest
                    ? $this->upgradeGuest($guest, $identity)
                    : User::create([...$identity, 'role' => 'user', 'status' => 'active']);
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

    /**
     * The guest behind this request's bearer token, if any. The sign-up routes carry no auth
     * middleware, so a missing, expired or invalid token — or a registered user's token, or a
     * suspended guest's — just yields null and sign-up creates a fresh account as before
     * (never a 401), rather than upgrading a suspended row back into a working account.
     */
    private function guestFromBearerToken(): ?User
    {
        $user = auth('sanctum')->user();

        return $user instanceof User && $user->isGuest() && $user->isActive() ? $user : null;
    }

    /**
     * Turns the guest row itself into the real account (rather than creating a new one) so its
     * decisions, saves and taste history carry over. Its guest tokens are revoked — the caller
     * issues a fresh one. Callers inside a transaction get both writes rolled back together.
     * `upgraded_from_guest_at` is what the admin guest-conversion stats count.
     *
     * @param  array<string, mixed>  $identity
     */
    private function upgradeGuest(User $guest, array $identity): User
    {
        $guest->tokens()->delete();
        $guest->forceFill([...$identity, 'is_guest' => false, 'upgraded_from_guest_at' => now()])->save();

        return $guest;
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
     * account creation. All the deletion side effects (Apple revocation, uploaded-photo cleanup,
     * token revocation, the audit trace, the forceDelete itself) live in
     * UserAccountDeletionService — this method is just password verification + orchestration.
     */
    public function destroy(DeleteAccountRequest $request, UserAccountDeletionService $deletion): JsonResponse
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

        $deletion->delete($user);

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

    public function updateProfile(UpdateMyProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('avatarKey', $data)) {
            $user->avatar_key = $data['avatarKey'];
        }

        if (array_key_exists('halalPreference', $data)) {
            $user->halal_preference = $data['halalPreference'];
        }

        $user->save();

        return response()->json(['user' => $this->presentUser($user)]);
    }

    /**
     * Records an agreement to the current Terms of Use + Community Guidelines (the app's
     * first-contribution sheet). Versions must match config/legal.php: agreeing to a document
     * that has since changed would record consent to text the user never saw.
     */
    public function acceptTerms(AcceptTermsRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['termsVersion'] !== config('legal.terms_version') || $data['guidelinesVersion'] !== config('legal.guidelines_version')) {
            return response()->json(['message' => 'The terms were updated. Please review them again.'], 422);
        }

        $user = $request->user();

        $user->termsAcceptances()->create([
            'terms_version' => $data['termsVersion'],
            'guidelines_version' => $data['guidelinesVersion'],
            'privacy_version' => $data['privacyVersion'],
            'context' => 'contribution',
            'app_version' => $data['appVersion'] ?? null,
            'accepted_at' => now(),
        ]);

        return response()->json(['user' => $this->presentUser($user)]);
    }

    private function presentUser(User $user): array
    {
        $user->loadMissing('affiliation.university', 'affiliation.area');

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'avatarKey' => $user->avatar_key,
            'halalPreference' => (bool) $user->halal_preference,
            'role' => $user->role,
            'status' => $user->status,
            'isGuest' => $user->isGuest(),
            'affiliationType' => $user->affiliation?->type,
            'university' => $user->universityShortName(),
            'area' => $user->areaShortName(),
            'affiliationVerificationStatus' => $user->affiliation?->verification_status,
            // Current document versions, so the app can show the agreement sheet (and send the
            // versions back) before a contribution instead of waiting for a terms_required 403.
            'legal' => [
                'termsVersion' => config('legal.terms_version'),
                'guidelinesVersion' => config('legal.guidelines_version'),
                'privacyVersion' => config('legal.privacy_version'),
                'needsAcceptance' => ! $user->hasAcceptedCurrentTerms(),
            ],
        ];
    }
}
