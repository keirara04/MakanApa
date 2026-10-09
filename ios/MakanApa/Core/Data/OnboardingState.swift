import Foundation
import Observation

/// Local state captured before an account exists. Persists across launches so someone who
/// explores, closes the app, and comes back doesn't repeat onboarding or lose their craving
/// picks. The picks reach Makan Brain (`POST me/selera/seed`) once there's an account to hold
/// them — see `sendSeedIfNeeded()`.
@MainActor
@Observable
final class OnboardingState {
    static let shared = OnboardingState()

    private enum Keys {
        static let completed = "OnboardingState.completed"
        static let cuisines = "OnboardingState.cuisines"
        static let seedSent = "OnboardingState.seedSent"
        static let tasteAsked = "OnboardingState.tasteAsked"
    }

    static let maxPicks = 4
    /// "No lean" — can't sit alongside specific picks.
    static let anythingPick = "anything"
    /// Stored picks are the server's keys (api TasteEventRecorder::ONBOARDING_SEEDS, which
    /// allowlists them) — labels live in `Copy.tasteLabel(_:)` so copy can change freely.
    static let foodPicks = ["malay", "mamak", "korean", "cafe", "dessert"]
    static let stylePicks = ["cheap_eats", "late_night"]

    private(set) var hasCompletedOnboarding: Bool
    private(set) var selectedCuisines: Set<String>

    private init() {
        let defaults = UserDefaults.standard
        hasCompletedOnboarding = defaults.bool(forKey: Keys.completed)
        // Older builds stored display labels ("Cheap eats", "Anything lah") — fold them to keys.
        selectedCuisines = Set((defaults.stringArray(forKey: Keys.cuisines) ?? []).map { stored in
            let key = stored.lowercased().replacingOccurrences(of: " ", with: "_")
            return key == "anything_lah" ? Self.anythingPick : key
        })
    }

    /// Cravings are asked after a real pick, not during onboarding. Anyone whose seed already
    /// went out (older onboarding) counts as asked.
    var shouldAskTaste: Bool {
        let defaults = UserDefaults.standard
        return !defaults.bool(forKey: Keys.tasteAsked) && !defaults.bool(forKey: Keys.seedSent)
    }

    /// Saved or dismissed — either way, don't ask again. Dismissing drops anything tapped so it
    /// can't be sent later on sign-in.
    func finishTastePrompt(saved: Bool) {
        UserDefaults.standard.set(true, forKey: Keys.tasteAsked)
        if saved {
            sendSeedIfNeeded()
        } else {
            selectedCuisines = []
            UserDefaults.standard.removeObject(forKey: Keys.cuisines)
        }
    }

    func toggleCuisine(_ cuisine: String) {
        if selectedCuisines.contains(cuisine) {
            selectedCuisines.remove(cuisine)
        } else if cuisine == Self.anythingPick {
            selectedCuisines = [cuisine]
        } else {
            selectedCuisines.remove(Self.anythingPick)
            guard selectedCuisines.count < Self.maxPicks else { return }
            selectedCuisines.insert(cuisine)
        }
        UserDefaults.standard.set(Array(selectedCuisines), forKey: Keys.cuisines)
    }

    func complete() {
        hasCompletedOnboarding = true
        UserDefaults.standard.set(true, forKey: Keys.completed)
        sendSeedIfNeeded()
    }

    /// Hands the picks to Makan Brain as weak starting hints — once, and only after onboarding
    /// is done and an account exists (onboarding runs before sign-in). Called on finishing
    /// onboarding and on every sign-in / launch, so a failed send retries next time. The server
    /// ignores a second seed for the same account.
    func sendSeedIfNeeded() {
        guard hasCompletedOnboarding, !UserDefaults.standard.bool(forKey: Keys.seedSent),
              case .authenticated = AuthStore.shared.session else { return }
        // Nothing picked yet isn't "sent" — the taste prompt may still fill it in later.
        let picks = selectedCuisines.sorted()
        guard !picks.isEmpty else { return }
        Task {
            guard (try? await APIClient.seedSelera(picks: picks)) != nil else { return }
            UserDefaults.standard.set(true, forKey: Keys.seedSent)
        }
    }
}
