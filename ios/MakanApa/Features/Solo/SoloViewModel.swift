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

    static let moodOptions = ["comfort_food", "spicy", "healthy", "quick", "late_night"]
    static let cuisineOptions = ["malay", "chinese", "japanese", "korean", "thai", "western", "indian"]
    static let budgetTiers = [1, 2, 3]
    static let distanceTiers: [Double] = [1.0, 2.0, 5.0]

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
