import XCTest
@testable import MakanApa

final class RecommendationEngineTests: XCTestCase {
    let originLat = 2.928400
    let originLng = 101.780200

    func makeRestaurant(
        id: Int,
        lat: Double,
        lng: Double,
        priceLevel: Int? = 1,
        rating: Double? = 4.0,
        isActive: Bool = true,
        cuisines: [String] = [],
        tags: [String] = []
    ) -> Restaurant {
        Restaurant(
            id: id, name: "R\(id)", latitude: lat, longitude: lng, address: nil,
            priceLevel: priceLevel, rating: rating, isActive: isActive,
            provider: "fixture", providerPlaceId: nil, openingHours: nil,
            cuisines: cuisines, tags: tags
        )
    }

    func makePreference(
        moodTags: [String] = [],
        cuisines: [String] = [],
        budgetMax: Int = 3,
        maxDistanceKm: Double = 2.0
    ) -> Preference {
        Preference(
            moodTags: moodTags, cuisines: cuisines, budgetMax: budgetMax,
            maxDistanceKm: maxDistanceKm, latitude: originLat, longitude: originLng
        )
    }

    func testExcludesRestaurantBeyondMaxDistance() {
        let near = makeRestaurant(id: 1, lat: originLat + 0.001, lng: originLng)
        let far = makeRestaurant(id: 2, lat: originLat + 0.5, lng: originLng)
        let preference = makePreference(maxDistanceKm: 2.0)

        let eligible = RecommendationEngine.eligibleRestaurants([near, far], preference: preference)

        XCTAssertTrue(eligible.contains { $0.restaurant.id == 1 })
        XCTAssertFalse(eligible.contains { $0.restaurant.id == 2 })
    }

    func testExcludesRestaurantOverBudget() {
        let affordable = makeRestaurant(id: 1, lat: originLat, lng: originLng, priceLevel: 1)
        let expensive = makeRestaurant(id: 2, lat: originLat, lng: originLng, priceLevel: 3)
        let preference = makePreference(budgetMax: 1)

        let eligible = RecommendationEngine.eligibleRestaurants([affordable, expensive], preference: preference)

        XCTAssertTrue(eligible.contains { $0.restaurant.id == 1 })
        XCTAssertFalse(eligible.contains { $0.restaurant.id == 2 })
    }

    func testExcludesInactiveRestaurant() {
        let active = makeRestaurant(id: 1, lat: originLat, lng: originLng, isActive: true)
        let inactive = makeRestaurant(id: 2, lat: originLat, lng: originLng, isActive: false)
        let preference = makePreference()

        let eligible = RecommendationEngine.eligibleRestaurants([active, inactive], preference: preference)

        XCTAssertTrue(eligible.contains { $0.restaurant.id == 1 })
        XCTAssertFalse(eligible.contains { $0.restaurant.id == 2 })
    }

    func testMoodMatchingRestaurantScoresHigherThanNonMatching() {
        let matching = makeRestaurant(id: 1, lat: originLat, lng: originLng, tags: ["comfort_food", "spicy"])
        let nonMatching = makeRestaurant(id: 2, lat: originLat, lng: originLng, tags: ["quick"])
        let preference = makePreference(moodTags: ["comfort_food", "spicy"])

        let scoreMatching = RecommendationScore.score(restaurant: matching, preference: preference, distanceKm: 0)
        let scoreNonMatching = RecommendationScore.score(restaurant: nonMatching, preference: preference, distanceKm: 0)

        XCTAssertGreaterThan(scoreMatching, scoreNonMatching)
    }

    func testAnythingPreferenceNormalizesWeightsTo100Max() {
        let restaurant = makeRestaurant(id: 1, lat: originLat, lng: originLng, rating: 5.0)
        let preference = makePreference()
        XCTAssertTrue(preference.isAnything)

        let score = RecommendationScore.score(restaurant: restaurant, preference: preference, distanceKm: 0)

        XCTAssertEqual(score, 100, accuracy: 0.001)
    }

    func testRerollExcludesCurrentPick() {
        let candidates = (1...5).map { i in
            ScoredRestaurant(restaurant: makeRestaurant(id: i, lat: originLat, lng: originLng), score: Double(100 - i))
        }
        let current = candidates[0]

        for _ in 0..<20 {
            let reroll = RecommendationEngine.pick(from: candidates, excluding: current)
            XCTAssertNotEqual(reroll?.id, current.id)
        }
    }
}
