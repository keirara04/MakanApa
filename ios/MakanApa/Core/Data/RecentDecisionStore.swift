import Foundation
import Observation

struct RecentDecision: Codable, Identifiable, Equatable {
    let id: Int // provider place id — names collide/change, this doesn't
    let name: String
    let latitude: Double
    let longitude: Double
    let foodCategory: String?
    let priceLevel: Int?
    let rating: Double?
    let timestamp: Date
    let source: String // "solo" | "nearby" | "search"
}

/// Last few accepted picks, most recent first, for Home's "Recent" block. Deliberately capped
/// and un-paginated for now — a "See all" history screen is a later addition if this gets used.
@MainActor
@Observable
final class RecentDecisionStore {
    static let shared = RecentDecisionStore()

    private static let key = "RecentDecisionStore.decisions"
    private static let limit = 5

    private let defaults: UserDefaults
    private(set) var decisions: [RecentDecision]

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        if let data = defaults.data(forKey: Self.key),
           let decoded = try? JSONDecoder().decode([RecentDecision].self, from: data) {
            decisions = decoded
        } else {
            decisions = []
        }
    }

    func record(_ decision: RecentDecision) {
        decisions.removeAll { $0.id == decision.id }
        decisions.insert(decision, at: 0)
        decisions = Array(decisions.prefix(Self.limit))
        if let data = try? JSONEncoder().encode(decisions) {
            defaults.set(data, forKey: Self.key)
        }
    }
}
