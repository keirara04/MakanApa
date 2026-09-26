import Foundation
import Observation

struct AuthUser: Codable, Equatable {
    let id: Int
    /// Nil for a guest account.
    let email: String?
    let name: String?
    let avatarKey: String?
    let role: String
    let status: String
    let affiliationType: String?
    let university: String?
    let area: String?
    /// "verified" (admin-assigned) | "self_reported" (picked in-app) | nil (no affiliation row
    /// yet). Not rendered anywhere yet — carried through now so a future "UKM ✓" verified badge
    /// doesn't need another auth-response shape change.
    let affiliationVerificationStatus: String?
    /// Optional: absent from older cached payloads / older backends.
    let halalPreference: Bool?
    /// Optional: absent from older cached payloads / older backends, which had no guests.
    let isGuest: Bool?

    var isSuperadmin: Bool { role == "superadmin" }
    var isGuestAccount: Bool { isGuest == true }
}

enum SessionState: Equatable {
    case loading
    case authenticated(AuthUser)
    case unauthenticated

    var isAuthenticated: Bool {
        if case .authenticated = self { return true }
        return false
    }

    /// A signed-in guest (see `AuthStore.continueAsGuest()`) — can use everything except
    /// account-based features, which show `AccountRequiredPrompt` instead.
    var isGuest: Bool {
        if case .authenticated(let user) = self { return user.isGuestAccount }
        return false
    }
}

/// Both /auth/apple and /auth/google resolve to one of these two shapes — see
/// `SocialLoginResponse`. `.needsLinking` carries only the opaque `linkToken` the backend issued
/// and an `email` for display; AuthStore never holds or transmits a provider subject id itself.
enum SocialLoginOutcome {
    case authenticated
    case needsLinking(linkToken: String, email: String)
}

/// Owns auth *state*, not token persistence (see CredentialStore). Three-state session — not a
/// plain isLoggedIn Bool — so the root view can show a brand splash while a stored token is
/// being validated against /auth/me, instead of flashing Login for a frame before landing on
/// the app shell.
@MainActor
@Observable
final class AuthStore {
    static let shared = AuthStore()

    private(set) var session: SessionState = .loading {
        didSet {
            // Sign-in (not every profile refresh): align the local Halal-only choice with the account.
            if case .authenticated(let user) = session, !oldValue.isAuthenticated {
                HalalPreference.reconcile(with: user)
            }
            switch session {
            case .authenticated(let user): Self.storeCachedUser(user)
            case .unauthenticated: Self.storeCachedUser(nil)
            case .loading: break
            }
        }
    }

    /// Set when a stored token exists but neither `/auth/me` nor a cached user could be used
    /// (first launch after install/update while offline) — the splash offers a retry instead of
    /// discarding a perfectly valid token.
    private(set) var bootstrapFailed = false

    private static let cachedUserKey = "AuthStore.cachedUser"

    private init() {}

    func bootstrap() async {
        #if DEBUG
        print("[AuthStore] bootstrap() called")
        #endif
        guard CredentialStore.shared.token != nil else {
            session = .unauthenticated
            return
        }
        bootstrapFailed = false

        // Open straight into the app with the last known profile — no splash wait on the network,
        // and no logout just because the phone is offline or the server is mid-deploy.
        if case .loading = session, let cached = Self.loadCachedUser() {
            session = .authenticated(cached)
        }

        do {
            let response = try await APIClient.me()
            session = .authenticated(response.user)
        } catch APIError.unauthorized {
            // The only case that actually means "this token is dead".
            handleUnauthorized()
        } catch {
            #if DEBUG
            print("[AuthStore] bootstrap() /me failed, keeping session: \(error)")
            #endif
            if case .loading = session {
                bootstrapFailed = true
            }
        }
    }

    private static func loadCachedUser() -> AuthUser? {
        guard let data = UserDefaults.standard.data(forKey: cachedUserKey) else { return nil }
        return try? JSONDecoder().decode(AuthUser.self, from: data)
    }

    private static func storeCachedUser(_ user: AuthUser?) {
        if let user, let data = try? JSONEncoder().encode(user) {
            UserDefaults.standard.set(data, forKey: cachedUserKey)
        } else {
            UserDefaults.standard.removeObject(forKey: cachedUserKey)
        }
    }

    func login(email: String, password: String) async throws {
        let response = try await APIClient.login(email: email, password: password, deviceLabel: Self.deviceLabel)
        CredentialStore.shared.token = response.token
        session = .authenticated(response.user)
        claimDeviceTokenIfPresent()
    }

    /// "Continue without an account" — App Review guideline 5.1.1(v): recommendations and
    /// Nearby aren't account-based, so they can't sit behind sign-up. Signing in later from a
    /// guest session upgrades this same account (register/Apple/Google send its token).
    func continueAsGuest() async throws {
        let response = try await APIClient.continueAsGuest(deviceLabel: Self.deviceLabel)
        CredentialStore.shared.token = response.token
        session = .authenticated(response.user)
        claimDeviceTokenIfPresent()
    }

    func register(name: String, email: String, password: String) async throws {
        let response = try await APIClient.register(name: name, email: email, password: password, deviceLabel: Self.deviceLabel)
        CredentialStore.shared.token = response.token
        session = .authenticated(response.user)
        claimDeviceTokenIfPresent()
    }

    func loginWithApple(identityToken: String, authorizationCode: String, rawNonce: String, fullName: String?) async throws -> SocialLoginOutcome {
        let response = try await APIClient.loginWithApple(
            identityToken: identityToken, authorizationCode: authorizationCode, nonce: rawNonce, fullName: fullName, deviceLabel: Self.deviceLabel
        )
        return try applySocialLoginResponse(response)
    }

    func loginWithGoogle(idToken: String) async throws -> SocialLoginOutcome {
        let response = try await APIClient.loginWithGoogle(idToken: idToken, deviceLabel: Self.deviceLabel)
        return try applySocialLoginResponse(response)
    }

    /// The client supplies only a password and the opaque linkToken — never a provider identity
    /// — the backend already verified the provider subject at the moment /auth/apple or
    /// /auth/google issued this token.
    func completeLink(password: String, linkToken: String) async throws {
        let response = try await APIClient.completeLink(password: password, linkToken: linkToken, deviceLabel: Self.deviceLabel)
        CredentialStore.shared.token = response.token
        session = .authenticated(response.user)
        claimDeviceTokenIfPresent()
    }

    private func applySocialLoginResponse(_ response: SocialLoginResponse) throws -> SocialLoginOutcome {
        if response.needsLinking == true, let linkToken = response.linkToken, let email = response.email {
            return .needsLinking(linkToken: linkToken, email: email)
        }

        guard let token = response.token, let user = response.user else {
            throw APIError.invalidResponse
        }

        CredentialStore.shared.token = token
        session = .authenticated(user)
        claimDeviceTokenIfPresent()
        return .authenticated
    }

    /// `university`/`area` both nil means an explicit Public selection, not "leave unchanged" —
    /// there is no partial-update variant of this call. The two are mutually exclusive; callers
    /// should never pass both non-nil (the backend rejects it).
    func updateCommunity(university: String?, area: String?) async throws {
        let response = try await APIClient.updateMyCommunity(university: university, area: area)
        session = .authenticated(response.user)
    }

    func updateProfile(name: String?, avatarKey: String?) async throws {
        let response = try await APIClient.updateMyProfile(name: name, avatarKey: avatarKey)
        session = .authenticated(response.user)
    }

    func logout() async {
        // Unclaims (not deletes) so the installation stays eligible for anonymous/transactional
        // pushes after the next `register()` call — see DeviceTokenController on the backend.
        try? await APIClient.unclaimDeviceToken(installationId: InstallationID.current, environment: PushEnvironment.current)
        _ = try? await APIClient.logout()
        CredentialStore.shared.token = nil
        session = .unauthenticated
    }

    /// `password` is only required for accounts that have one — pass `nil` for a social-only
    /// account, the backend skips the check for those since the Sanctum token already proves a
    /// recent sign-in.
    func deleteAccount(password: String?) async throws {
        _ = try await APIClient.deleteAccount(password: password)
        CredentialStore.shared.token = nil
        session = .unauthenticated
    }

    /// Called from call sites that catch `APIError.unauthorized` — a mid-session revoke or an
    /// expired token surfaces as a 401 on the next request, not just at launch, so this is the
    /// one place that reaction is handled rather than every ViewModel re-implementing it.
    func handleUnauthorized() {
        #if DEBUG
        print("[AuthStore] handleUnauthorized() called — clearing token, bouncing to login")
        #endif
        CredentialStore.shared.token = nil
        session = .unauthenticated
    }

    /// Fires the claim call using whatever APNs token this install already registered (if any) —
    /// a no-op until `PushNotificationDelegate` has actually received one from the OS.
    private func claimDeviceTokenIfPresent() {
        guard let token = DeviceTokenStore.current else { return }
        Task {
            try? await APIClient.claimDeviceToken(installationId: InstallationID.current, token: token, environment: PushEnvironment.current)
        }
    }

    /// A non-personal Sanctum token label — purely for the admin's own reference — rather than
    /// the user's actual device name.
    private static var deviceLabel: String {
        let suffix = InstallationID.current.suffix(4)
        return "MakanApa iOS · \(suffix)"
    }
}
