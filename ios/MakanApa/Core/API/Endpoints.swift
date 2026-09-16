import Foundation

struct SoloRecommendationRequestBody: Encodable {
    let latitude: Double
    let longitude: Double
    let budgetMax: Int?
    let maxDistanceKm: Double
    let moods: [String]
}

struct RecommendationResponse: Decodable, Equatable {
    struct PhotoAttribution: Decodable, Equatable {
        let name: String?
        let profileUrl: String?
        let photoUrl: String?
    }

    struct Photo: Decodable, Equatable {
        let url: String
        let authorAttributions: [PhotoAttribution]
        let googleMapsUrl: String?
        let flagContentUrl: String?
    }

    struct Review: Decodable, Equatable {
        let text: String
        let rating: Double?
        let authorName: String
        let authorProfileUrl: String?
        let authorPhotoUrl: String?
        let relativePublishTime: String?
        let googleMapsUrl: String?
        let flagContentUrl: String?
    }

    struct Recommendation: Decodable, Equatable {
        let id: Int
        let name: String
        let headline: String
        let foodCategory: String?
        let latitude: Double
        let longitude: Double
        let distanceKm: Double
        let rating: Double?
        let priceLevel: Int?
        let cuisines: [String]
        let openStatus: String
        let photos: [Photo]
        let reviews: [Review]
        let placeGoogleMapsUrl: String?
        let closesAt: String?
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
