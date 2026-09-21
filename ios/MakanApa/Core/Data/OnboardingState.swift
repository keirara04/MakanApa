import Foundation
import Observation

/// Local-only guest state captured before an account exists. Persists across launches so a
/// guest who explores, closes the app, and comes back doesn't repeat onboarding or lose their
/// craving picks. Merge this into the account (`PATCH me/community`-style call) once the backend
/// exposes a guest-preferences endpoint — not done yet, see MakanApaApp.swift routing note.
@MainActor
@Observable
final class OnboardingState {
    static let shared = OnboardingState()

    private enum Keys {
        static let completed = "OnboardingState.completed"
        static let cuisines = "OnboardingState.cuisines"
    }

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
        } else if selectedCuisines.count < 4 {
            selectedCuisines.insert(cuisine)
        }
        UserDefaults.standard.set(Array(selectedCuisines), forKey: Keys.cuisines)
    }

    func complete() {
        hasCompletedOnboarding = true
        UserDefaults.standard.set(true, forKey: Keys.completed)
    }
}
