import Foundation

struct ScoredRestaurant: Identifiable, Equatable {
    let restaurant: Restaurant
    let score: Double
    let distanceKm: Double
    var id: Int { restaurant.id }
}

enum RecommendationEngine {
    /// Rank weights for weighted-random pick among the top 5 candidates.
    static let rankWeights: [Double] = [0.40, 0.25, 0.17, 0.11, 0.07]

    static func eligibleRestaurants(_ restaurants: [Restaurant], preference: Preference) -> [(restaurant: Restaurant, distanceKm: Double)] {
        restaurants.compactMap { restaurant in
            guard restaurant.isActive else { return nil }
            if let priceLevel = restaurant.priceLevel, priceLevel > preference.budgetMax {
                return nil
            }
            let distance = RecommendationScore.distanceKm(
                lat1: preference.latitude, lon1: preference.longitude,
                lat2: restaurant.latitude, lon2: restaurant.longitude
            )
            guard distance <= preference.maxDistanceKm else { return nil }
            return (restaurant, distance)
        }
    }

    static func topCandidates(from restaurants: [Restaurant], preference: Preference, limit: Int = 5) -> [ScoredRestaurant] {
        let eligible = eligibleRestaurants(restaurants, preference: preference)
        let scored = eligible.map { pair in
            ScoredRestaurant(
                restaurant: pair.restaurant,
                score: RecommendationScore.score(restaurant: pair.restaurant, preference: preference, distanceKm: pair.distanceKm),
                distanceKm: pair.distanceKm
            )
        }
        return scored.sorted { $0.score > $1.score }.prefix(limit).map { $0 }
    }

    /// Weighted-random pick over ranked candidates, optionally excluding one (for reroll).
    static func pick(from candidates: [ScoredRestaurant], excluding excluded: ScoredRestaurant? = nil, randomSource: () -> Double = { Double.random(in: 0..<1) }) -> ScoredRestaurant? {
        var pool = candidates
        if let excluded, pool.count > 1 {
            pool.removeAll { $0.id == excluded.id }
        }
        guard !pool.isEmpty else { return nil }

        let weights = rankWeights.prefix(pool.count)
        let totalWeight = weights.reduce(0, +)
        var roll = randomSource() * totalWeight

        for (index, restaurant) in pool.enumerated() {
            let weight = index < weights.count ? weights[weights.startIndex + index] : 0
            if roll < weight {
                return restaurant
            }
            roll -= weight
        }
        return pool.last
    }

    static func recommend(from restaurants: [Restaurant], preference: Preference) -> (candidates: [ScoredRestaurant], pick: ScoredRestaurant?) {
        let candidates = topCandidates(from: restaurants, preference: preference)
        return (candidates, pick(from: candidates))
    }
}
