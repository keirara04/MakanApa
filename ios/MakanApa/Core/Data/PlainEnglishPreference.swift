import Foundation
import Observation

/// Settings → Plain English. Manglish is MakanApa's voice and stays the default; this swaps only
/// the few Malay-only lines a non-Malay speaker can't read (the Plain English section of `Copy`).
/// Stored locally so it applies instantly, and synced to the account — the server writes the
/// "Right now" strip, pick reasons and mealtime pushes, and reads the setting from there.
@MainActor
@Observable
final class PlainEnglishPreference {
    static let shared = PlainEnglishPreference()

    private static let key = "settings.plainEnglish"
    private static let answeredKey = "settings.plainEnglish.answered"

    private(set) var isOn: Bool

    private init() {
        isOn = UserDefaults.standard.bool(forKey: Self.key)
    }

    /// Manglish by default, `plain` when the setting is on. Read inside a view's body, so that
    /// view re-renders the moment the setting changes.
    func pick(_ manglish: String, plain: String) -> String {
        isOn ? plain : manglish
    }

    /// Settings toggle: local first (instant), then the account.
    func set(_ value: Bool) {
        store(value)
        guard case .authenticated = AuthStore.shared.session else { return }
        Task { _ = try? await APIClient.updatePlainEnglish(value) }
    }

    /// On sign-in: the local choice wins once made; otherwise adopt the account's saved value.
    func reconcile(with user: AuthUser) {
        if UserDefaults.standard.bool(forKey: Self.answeredKey) {
            if user.plainEnglish != isOn {
                let value = isOn
                Task { _ = try? await APIClient.updatePlainEnglish(value) }
            }
        } else if let saved = user.plainEnglish {
            store(saved)
        }
    }

    private func store(_ value: Bool) {
        isOn = value
        UserDefaults.standard.set(value, forKey: Self.key)
        UserDefaults.standard.set(true, forKey: Self.answeredKey)
    }
}
