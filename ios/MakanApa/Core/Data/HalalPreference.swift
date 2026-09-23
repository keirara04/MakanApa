import Foundation

/// "Halal only" — a standing dietary requirement, not a browsing chip. Stored locally so it
/// works before sign-up (it's asked during onboarding) and applies instantly; every
/// recommendation/nearby request sends it explicitly (`halal`), which the backend prefers over
/// the account's stored value. Synced to the account whenever signed in.
enum HalalPreference {
    private static let key = "settings.halalOnly"
    private static let answeredKey = "settings.halalOnly.answered"

    static var isOn: Bool {
        get { UserDefaults.standard.bool(forKey: key) }
        set {
            UserDefaults.standard.set(newValue, forKey: key)
            UserDefaults.standard.set(true, forKey: answeredKey)
        }
    }

    /// Whether the user ever chose — lets a signed-in account's saved value win on a fresh install.
    static var hasAnswered: Bool { UserDefaults.standard.bool(forKey: answeredKey) }

    /// Local choice wins once made; otherwise adopt the account's saved value.
    @MainActor
    static func reconcile(with user: AuthUser) {
        if hasAnswered {
            if user.halalPreference != isOn {
                let value = isOn
                Task { _ = try? await APIClient.updateHalalPreference(value) }
            }
        } else if let saved = user.halalPreference {
            isOn = saved
        }
    }

    /// Settings toggle: local first (instant), then the account.
    @MainActor
    static func set(_ value: Bool) {
        isOn = value
        guard case .authenticated = AuthStore.shared.session else { return }
        Task { _ = try? await APIClient.updateHalalPreference(value) }
    }
}
