import Foundation

struct SoloRecommendationRequestBody: Encodable {
    let latitude: Double
    let longitude: Double
    let budgetMax: Int?
    let maxDistanceKm: Double
    let moods: [String]
}

struct RecommendationResponse: Decodable, Equatable {
    struct Recommendation: Decodable, Equatable {
        let id: Int
        let name: String
        let headline: String
        let latitude: Double
        let longitude: Double
        let distanceKm: Double
        let rating: Double?
        let priceLevel: Int?
        let cuisines: [String]
        let openStatus: String
    }

    let decisionId: Int
    let algorithmVersion: String
    let recommendation: Recommendation?
}

struct RerollResponse: Decodable {
    let recommendation: RecommendationResponse.Recommendation?
}

struct AcceptResponse: Decodable {
    let accepted: Bool
}
