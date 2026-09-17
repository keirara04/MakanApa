import Foundation

struct SoloRecommendationRequestBody: Encodable {
    let latitude: Double
    let longitude: Double
    let budgetMax: Int?
    let maxDistanceKm: Double
    let moods: [String]
    let craving: String?
    let mode: DiscoveryMode?
    let vibe: Vibe?
    let installationId: String?
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
        /// Only present once a restaurant has enough CommunityTag votes to clear the backend's
        /// confidence threshold (PresentsRecommendation::communityTagBadge()) — absent, not a
        /// low-confidence guess, below that bar.
        let communityTag: CommunityTag?
    }

    /// Whether the typed craving (if any) matched something nearby — decoded but not yet
    /// surfaced in UI; a future pass can render an honest "couldn't find an exact match" state.
    struct CravingMatch: Decodable, Equatable {
        let query: String
        let matched: Bool
        let resolvedAs: String?
        let source: String?
        let confidence: Double?
    }

    let decisionId: Int
    let clientToken: String
    let algorithmVersion: String
    let recommendation: Recommendation?
    let craving: CravingMatch?
}

struct RerollResponse: Decodable {
    let recommendation: RecommendationResponse.Recommendation?
}

struct AcceptResponse: Decodable {
    let accepted: Bool
}

struct MapViewport: Encodable {
    let north: Double
    let south: Double
    let east: Double
    let west: Double
}

struct NearbyPlace: Decodable, Equatable, Identifiable {
    let id: Int
    let name: String
    let rating: Double?
    let priceLevel: Int?
    let latitude: Double
    let longitude: Double
    let openStatus: String
}

struct NearbyPlacesResponse: Decodable {
    let places: [NearbyPlace]
}

struct NearbyPickRequestBody: Encodable {
    let latitude: Double
    let longitude: Double
    let viewport: MapViewport
    let visiblePlaceIds: [Int]
    let openNow: Bool?
    let budgetMax: Int?
    let minRating: Double?
    let mode: DiscoveryMode?
    let vibe: Vibe?
    let installationId: String?
}

struct SaveRequestBody: Encodable {
    let installationId: String
}

struct SaveResponse: Decodable {
    let saved: Bool
}

struct VibeTagRequestBody: Encodable {
    let vibe: CommunityTag
}

struct VibeTagResponse: Decodable {
    let tagged: Bool
}

/// Same wire shape as `RecommendationResponse` — Nearby's "Pick one lah" ends a decision
/// exactly like Decide does, so it reuses ResultView's model rather than a parallel one.
typealias NearbyPickResponse = RecommendationResponse

/// Winner-only enrichment for a single marker, fetched when its bottom sheet opens — never
/// for the whole visible marker list. Reuses `RecommendationResponse`'s Photo/Review shapes
/// since they're the same wire format.
struct PlaceDetails: Decodable, Equatable {
    let id: Int
    let name: String
    let foodCategory: String?
    let rating: Double?
    let priceLevel: Int?
    let cuisines: [String]
    let openStatus: String
    let photos: [RecommendationResponse.Photo]
    let reviews: [RecommendationResponse.Review]
    let placeGoogleMapsUrl: String?
    let closesAt: String?
}
