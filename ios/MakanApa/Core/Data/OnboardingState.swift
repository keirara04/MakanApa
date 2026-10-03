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
    }

    static let maxPicks = 4
    /// "No lean" — can't sit alongside specific picks.
    static let anythingPick = "Anything lah"
    /// Chip label → the server's key (api TasteEventRecorder::ONBOARDING_SEEDS).
    static let seedKeys: [String: String] = [
        "Mamak": "mamak", "Cafe": "cafe", "Korean": "korean", "Malay": "malay", "Dessert": "dessert",
        "Cheap eats": "cheap_eats", "Late night": "late_night", anythingPick: "anything",
    ]

    private(set) var hasCompletedOnboarding: Bool
    private(set) var selectedCuisines: Set<String>

    private init() {
        let defaults = UserDefaults.standard
        hasCompletedOnboarding = defaults.bool(forKey: Keys.completed)
        selectedCuisines = Set(defaults.stringArray(forKey: Keys.cuisines) ?? [])
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
        let picks = selectedCuisines.compactMap { Self.seedKeys[$0] }.sorted()
        guard !picks.isEmpty else {
            UserDefaults.standard.set(true, forKey: Keys.seedSent)
            return
        }
        Task {
            guard (try? await APIClient.seedSelera(picks: picks)) != nil else { return }
            UserDefaults.standard.set(true, forKey: Keys.seedSent)
        }
    }
}
