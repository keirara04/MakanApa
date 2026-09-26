import Foundation
import Observation

/// When to suggest a guest makes an account — only after the app has already proven useful
/// (a few accepted picks, a few saved places), never at launch and never blocking anything.
/// Each reason shows until it's dismissed once, and any dismissal snoozes every nudge for a
/// week. Counts only move while the session is a guest; a real account never sees a nudge.
/// Persisted the same way `NotificationPrimingState` is, so it survives relaunches.
@MainActor
@Observable
final class GuestUpgradeNudge {
    static let shared = GuestUpgradeNudge()

    enum Reason: String {
        case picks
        case saves

        /// Sent as `AuthStore.signupSource` when a sign-up comes from this nudge.
        var signupSource: String { "nudge_\(rawValue)" }
    }

    static let threshold = 3
    static let snoozeInterval: TimeInterval = 7 * 24 * 60 * 60

    private static let picksKey = "GuestUpgradeNudge.acceptedPicks"
    private static let savesKey = "GuestUpgradeNudge.saves"
    private static let dismissedKey = "GuestUpgradeNudge.dismissedReasons"
    private static let snoozedUntilKey = "GuestUpgradeNudge.snoozedUntil"

    private let defaults: UserDefaults
    private(set) var acceptedPicks: Int
    private(set) var saves: Int
    private(set) var dismissedReasons: Set<String>
    private(set) var snoozedUntil: Date?
    /// ResultView can accept the same pick twice (👍, then "Jom makan") — count a decision once.
    @ObservationIgnored private var lastCountedDecisionId: Int?

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        acceptedPicks = defaults.integer(forKey: Self.picksKey)
        saves = defaults.integer(forKey: Self.savesKey)
        dismissedReasons = Set(defaults.stringArray(forKey: Self.dismissedKey) ?? [])
        snoozedUntil = defaults.object(forKey: Self.snoozedUntilKey) as? Date
    }

    func recordAcceptedPick(decisionId: Int) {
        guard AuthStore.shared.session.isGuest, decisionId != lastCountedDecisionId else { return }
        lastCountedDecisionId = decisionId
        acceptedPicks += 1
        defaults.set(acceptedPicks, forKey: Self.picksKey)
    }

    func recordSave() {
        guard AuthStore.shared.session.isGuest else { return }
        saves += 1
        defaults.set(saves, forKey: Self.savesKey)
    }

    /// Whether this reason's card may show right now.
    func isEligible(_ reason: Reason, now: Date = Date()) -> Bool {
        guard AuthStore.shared.session.isGuest, !dismissedReasons.contains(reason.rawValue) else { return false }
        if let snoozedUntil, snoozedUntil > now { return false }
        switch reason {
        case .picks: return acceptedPicks >= Self.threshold
        case .saves: return saves >= Self.threshold
        }
    }

    /// The one card Home shows — picks first, since that's the app's core loop.
    var activeReason: Reason? {
        [Reason.picks, .saves].first { isEligible($0) }
    }

    func dismiss(_ reason: Reason, now: Date = Date()) {
        dismissedReasons.insert(reason.rawValue)
        snoozedUntil = now.addingTimeInterval(Self.snoozeInterval)
        defaults.set(Array(dismissedReasons), forKey: Self.dismissedKey)
        defaults.set(snoozedUntil, forKey: Self.snoozedUntilKey)
    }
}
