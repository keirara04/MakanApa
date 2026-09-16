import Foundation
import Observation

/// Hardcoded user location near the fixture test area (Bangi). Skips CoreLocation for Phase 1.
private let defaultLatitude = 2.928400
private let defaultLongitude = 101.780200

@Observable
final class SoloViewModel {
    private(set) var restaurants: [Restaurant] = []
    private(set) var loadError: Error?

    var selectedMoodTags: Set<String> = []
    var selectedCuisines: Set<String> = []
    var budgetMax: Int = 2
    var maxDistanceKm: Double = 2.0

    private(set) var candidates: [ScoredRestaurant] = []
    private(set) var currentPick: ScoredRestaurant?

    struct MoodOption {
        let tag: String
        let emoji: String
        let label: String
    }

    struct BudgetOption {
        let tier: Int
        let symbol: String
        let label: String
    }

    struct DistanceOption {
        let km: Double
        let emoji: String
        let label: String
    }

    static let moodOptions: [MoodOption] = [
        MoodOption(tag: "spicy", emoji: "🔥", label: "Spicy"),
        MoodOption(tag: "comfort_food", emoji: "🍜", label: "Comfort"),
        MoodOption(tag: "healthy", emoji: "🥗", label: "Light"),
        MoodOption(tag: "quick", emoji: "⚡", label: "Quick"),
    ]

    static let cuisineOptions = ["malay", "chinese", "japanese", "korean", "thai", "western", "indian"]

    static let budgetOptions: [BudgetOption] = [
        BudgetOption(tier: 1, symbol: "RM", label: "Cheap"),
        BudgetOption(tier: 2, symbol: "RM RM", label: "Okay"),
        BudgetOption(tier: 3, symbol: "RM RM RM", label: "Treat"),
    ]

    static let distanceOptions: [DistanceOption] = [
        DistanceOption(km: 1.0, emoji: "🚶", label: "5 min"),
        DistanceOption(km: 2.0, emoji: "🚶‍♂️", label: "10 min"),
        DistanceOption(km: 5.0, emoji: "🚗", label: "Don't mind"),
    ]

    init() {
        loadFixture()
    }

    func loadFixture() {
        do {
            restaurants = try FixtureLoader.loadRestaurants()
        } catch {
            loadError = error
        }
    }

    private func currentPreference() -> Preference {
        Preference(
            moodTags: Array(selectedMoodTags),
            cuisines: Array(selectedCuisines),
            budgetMax: budgetMax,
            maxDistanceKm: maxDistanceKm,
            latitude: defaultLatitude,
            longitude: defaultLongitude
        )
    }

    func decide() {
        let result = RecommendationEngine.recommend(from: restaurants, preference: currentPreference())
        candidates = result.candidates
        currentPick = result.pick
    }

    func reroll() {
        currentPick = RecommendationEngine.pick(from: candidates, excluding: currentPick)
    }
}
