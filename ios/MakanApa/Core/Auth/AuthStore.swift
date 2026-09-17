import Foundation
import Observation

struct AuthUser: Codable, Equatable {
    let id: Int
    let email: String
    let role: String
    let status: String

    var isSuperadmin: Bool { role == "superadmin" }
}

enum SessionState: Equatable {
    case loading
    case authenticated(AuthUser)
    case unauthenticated
}

/// Owns auth *state*, not token persistence (see CredentialStore). Three-state session — not a
/// plain isLoggedIn Bool — so the root view can show a brand splash while a stored token is
/// being validated against /auth/me, instead of flashing Login for a frame before landing on
/// the app shell.
@MainActor
@Observable
final class AuthStore {
    static let shared = AuthStore()

    private(set) var session: SessionState = .loading

    private init() {}

    func bootstrap() async {
        guard CredentialStore.shared.token != nil else {
            session = .unauthenticated
            return
        }
        do {
            let response = try await APIClient.me()
            session = .authenticated(response.user)
        } catch {
            CredentialStore.shared.token = nil
            session = .unauthenticated
        }
    }

    func login(email: String, password: String) async throws {
        let response = try await APIClient.login(email: email, password: password, deviceLabel: Self.deviceLabel)
        CredentialStore.shared.token = response.token
        session = .authenticated(response.user)
    }

    func logout() async {
        _ = try? await APIClient.logout()
        CredentialStore.shared.token = nil
        session = .unauthenticated
    }

    /// Called from call sites that catch `APIError.unauthorized` — a mid-session revoke or an
    /// expired token surfaces as a 401 on the next request, not just at launch, so this is the
    /// one place that reaction is handled rather than every ViewModel re-implementing it.
    func handleUnauthorized() {
        CredentialStore.shared.token = nil
        session = .unauthenticated
    }

    /// A non-personal Sanctum token label — purely for the admin's own reference — rather than
    /// the user's actual device name.
    private static var deviceLabel: String {
        let suffix = InstallationID.current.suffix(4)
        return "MakanApa iOS · \(suffix)"
    }
}
