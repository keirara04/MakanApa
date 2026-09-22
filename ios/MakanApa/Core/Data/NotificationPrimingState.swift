import Foundation
import Observation

/// Tracks whether the notification priming screen has been shown — runs once, right after
/// onboarding, before the auth gate (see MakanApaApp.swift). Persisted the same way
/// `OnboardingState.hasCompletedOnboarding` is, so it survives relaunches without repeating.
@MainActor
@Observable
final class NotificationPrimingState {
    static let shared = NotificationPrimingState()

    private static let key = "NotificationPrimingState.hasSeenPriming"

    private(set) var hasSeenPriming: Bool

    private init() {
        hasSeenPriming = UserDefaults.standard.bool(forKey: Self.key)
    }

    func complete() {
        hasSeenPriming = true
        UserDefaults.standard.set(true, forKey: Self.key)
    }
}
