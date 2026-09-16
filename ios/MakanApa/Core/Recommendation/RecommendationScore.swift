import Foundation

/// MakanApa Recommendation v1 scoring weights and math.
/// Mirrors `api/app/Services/RecommendationService.php` exactly — keep both in sync.
enum RecommendationScore {
    static let moodWeight: Double = 30
    static let cuisineWeight: Double = 25
    static let budgetWeight: Double = 15
    static let distanceWeight: Double = 15
    static let ratingWeight: Double = 15

    /// Haversine distance in kilometers.
    static func distanceKm(lat1: Double, lon1: Double, lat2: Double, lon2: Double) -> Double {
        let earthRadiusKm = 6371.0
        let dLat = (lat2 - lat1) * .pi / 180
        let dLon = (lon2 - lon1) * .pi / 180
        let a = sin(dLat / 2) * sin(dLat / 2)
            + cos(lat1 * .pi / 180) * cos(lat2 * .pi / 180) * sin(dLon / 2) * sin(dLon / 2)
        let c = 2 * atan2(sqrt(a), sqrt(1 - a))
        return earthRadiusKm * c
    }

    static func tagMatchComponent(selected: [String], candidate: [String]) -> Double {
        guard !selected.isEmpty else { return 0 }
        let candidateSet = Set(candidate)
        let matched = selected.filter { candidateSet.contains($0) }.count
        return Double(matched) / Double(selected.count)
    }

    static func distanceComponent(distanceKm: Double, maxDistanceKm: Double) -> Double {
        guard maxDistanceKm > 0 else { return 0 }
        return max(0, 1 - distanceKm / maxDistanceKm)
    }

    static func ratingComponent(rating: Double?) -> Double {
        guard let rating else { return 0.5 }
        return min(1, max(0, rating / 5.0))
    }

    /// Final 0-100 score for a restaurant given active preference dimensions,
    /// normalizing weights when mood/cuisine are unselected.
    static func score(
        restaurant: Restaurant,
        preference: Preference,
        distanceKm: Double
    ) -> Double {
        var activeWeights: [(weight: Double, component: Double)] = []

        if !preference.moodTags.isEmpty {
            activeWeights.append((moodWeight, tagMatchComponent(selected: preference.moodTags, candidate: restaurant.tags)))
        }
        if !preference.cuisines.isEmpty {
            activeWeights.append((cuisineWeight, tagMatchComponent(selected: preference.cuisines, candidate: restaurant.cuisines)))
        }
        activeWeights.append((budgetWeight, 1.0))
        activeWeights.append((distanceWeight, distanceComponent(distanceKm: distanceKm, maxDistanceKm: preference.maxDistanceKm)))
        activeWeights.append((ratingWeight, ratingComponent(rating: restaurant.rating)))

        let totalWeight = activeWeights.reduce(0) { $0 + $1.weight }
        guard totalWeight > 0 else { return 0 }

        let weightedSum = activeWeights.reduce(0.0) { $0 + ($1.weight / totalWeight) * $1.component * 100 }
        return weightedSum
    }
}
