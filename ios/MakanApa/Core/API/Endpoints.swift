import Foundation

struct LoginRequestBody: Encodable {
    let email: String
    let password: String
    let deviceLabel: String
}

struct LoginResponse: Decodable {
    let token: String
    let user: AuthUser
}

struct MeResponse: Decodable {
    let user: AuthUser
}

struct LogoutResponse: Decodable {
    let loggedOut: Bool
}

struct AdminUserListResponse: Decodable {
    let users: [AdminUser]
}

struct AdminUser: Decodable, Identifiable, Equatable {
    let id: Int
    let email: String
    let role: String
    let status: String
    let createdAt: String
    /// "university" | "public" | nil (legacy account created before affiliation existed).
    /// Kept distinct from `university` being nil so Public and "no affiliation row" never collapse
    /// into the same on-screen blank state.
    let affiliationType: String?
    let university: String?
}

struct CreateBetaUserRequestBody: Encodable {
    let email: String
    let university: String?
}

struct CreateBetaUserResponse: Decodable {
    let user: AdminUser
    let temporaryPassword: String
}

struct RevokeUserResponse: Decodable {
    let revoked: Bool
}

struct UpdateMyCommunityRequestBody: Encodable {
    let university: String?
}

struct UniversityOption: Decodable, Identifiable, Equatable {
    let shortName: String
    let name: String

    var id: String { shortName }
}

struct UniversitiesResponse: Decodable {
    let universities: [UniversityOption]
}

struct CommunityInfo: Decodable, Equatable {
    let type: String
    let university: String?
    let label: String
}

struct CommunityFeedItem: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let foodCategory: String?
    let rating: Double?
    let priceLevel: Int?
    let cuisines: [String]
    let openStatus: String
    let pickCount: Int
    let pickerCount: Int
    let distanceKm: Double?
    let trendingVibe: String?
}

struct CommunityFeedResponse: Decodable, Equatable {
    let community: CommunityInfo
    let trending: [CommunityFeedItem]
}

// MARK: - Community places (submissions)

struct ExistingPlaceResult: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let address: String?
    let foodCategory: String?
    let priceLevel: Int?
    let distanceKm: Double?
}

struct GooglePlaceCandidate: Decodable, Identifiable, Equatable {
    let googlePlaceId: String
    let name: String
    let foodCategory: String?
    let priceLevel: Int?
    let rating: Double?
    let latitude: Double
    let longitude: Double

    var id: String { googlePlaceId }
}

struct PlaceSearchResponse: Decodable, Equatable {
    let existing: [ExistingPlaceResult]
    let google: [GooglePlaceCandidate]
}

/// What kind of change this submission proposes.
enum SubmissionType: String, Codable {
    case newPlace = "new_place"
    case editPlace = "edit_place"
    case closure
    case reopen
}

struct MenuItem: Codable, Equatable, Identifiable {
    var name: String
    var description: String? = nil
    var price: Double? = nil
    var category: String? = nil

    var id: String { name }
}

enum SubmissionSourceType: String, Codable {
    case google
    case manual
}

enum SubmissionLocationSource: String, Codable {
    case google
    case currentLocation = "current_location"
    case mapPin = "map_pin"
}

struct CreateSubmissionRequestBody: Encodable {
    let submissionType: SubmissionType
    let sourceType: SubmissionSourceType
    let googlePlaceId: String?
    let restaurantId: Int?
    let name: String
    let address: String?
    let foodCategory: String?
    let priceLevel: Int?
    let phone: String?
    let instagramHandle: String?
    let tiktokHandle: String?
    let websiteUrl: String?
    let menuItems: [MenuItem]?
    let latitude: Double
    let longitude: Double
    let locationSource: SubmissionLocationSource
    let notes: String?
    let changedFields: [String]
}

struct UpdateSubmissionRequestBody: Encodable {
    let name: String
    let address: String?
    let foodCategory: String?
    let priceLevel: Int?
    let phone: String?
    let instagramHandle: String?
    let tiktokHandle: String?
    let websiteUrl: String?
    let menuItems: [MenuItem]?
    let notes: String?
    let changedFields: [String]
}

struct SubmissionResponse: Decodable {
    let submission: MySubmission
}

struct MySubmission: Decodable, Identifiable, Equatable {
    let id: Int
    let restaurantId: Int?
    let submissionType: SubmissionType
    let sourceType: SubmissionSourceType
    let name: String
    let address: String?
    let foodCategory: String?
    let priceLevel: Int?
    let phone: String?
    let instagramHandle: String?
    let tiktokHandle: String?
    let websiteUrl: String?
    let menuItems: [MenuItem]?
    let status: String
    let reviewNote: String?
    let createdAt: String
}

struct MySubmissionsResponse: Decodable {
    let submissions: [MySubmission]
}

struct CancelSubmissionResponse: Decodable {
    let cancelled: Bool
}

struct SubmitSubmissionResponse: Decodable {
    let submission: MySubmission
}

struct UploadPhotoResponse: Decodable {
    struct Photo: Decodable { let id: Int; let photoType: String }
    let photo: Photo
}

// MARK: - Admin: community places moderation

struct AdminSubmissionSubmitter: Decodable, Equatable {
    let email: String?
    let affiliationType: String?
    let university: String?
}

struct PossibleDuplicate: Decodable, Equatable {
    let id: Int
    let name: String
    let distanceMeters: Int
}

struct AdminSubmission: Decodable, Identifiable, Equatable {
    let id: Int
    let submissionType: SubmissionType
    let sourceType: SubmissionSourceType
    let name: String
    let address: String?
    let foodCategory: String?
    let priceLevel: Int?
    let phone: String?
    let instagramHandle: String?
    let tiktokHandle: String?
    let websiteUrl: String?
    let menuItems: [MenuItem]?
    let changedFields: [String]
    let latitude: Double
    let longitude: Double
    let notes: String?
    let status: String
    let restaurantId: Int?
    let submitter: AdminSubmissionSubmitter
    let possibleDuplicate: PossibleDuplicate?
    let createdAt: String
}

struct AdminSubmissionListResponse: Decodable {
    let submissions: [AdminSubmission]
}

struct AdminSubmissionPhoto: Decodable, Identifiable, Equatable {
    let id: Int
    let photoType: String
    let url: String
}

struct AdminSubmissionPhotosResponse: Decodable {
    let photos: [AdminSubmissionPhoto]
}

struct ReleaseFieldOverrideResponse: Decodable {
    let released: Bool
}

struct AdminApproveResponse: Decodable {
    let approved: Bool
    let restaurantId: Int
}

struct LinkSubmissionRequestBody: Encodable {
    let restaurantId: Int
}

struct AdminLinkResponse: Decodable {
    let linked: Bool
}

struct ReviewNoteRequestBody: Encodable {
    let reviewNote: String
}

struct AdminRejectResponse: Decodable {
    let rejected: Bool
}

struct AdminRequestChangesResponse: Decodable {
    let changesRequested: Bool
}

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

// MARK: - Place search (Nearby search capsule)

/// Where a search result came from — drives ranking display (`◇ Community find`) and whether
/// selecting it needs a `resolvePlace` round-trip before a canonical `restaurantId` exists.
enum PlaceSearchProvenance: String, Decodable {
    case canonical
    case community
    case googleFallback = "google_fallback"
}

struct PlaceSearchResult: Decodable, Identifiable, Equatable {
    let provenance: PlaceSearchProvenance
    /// Present for `.canonical`/`.community`; nil for `.googleFallback` until resolved.
    let restaurantId: Int?
    /// Present only for `.googleFallback`.
    let googlePlaceId: String?
    let name: String
    let category: String?
    let cuisine: String?
    let distanceKm: Double?
    let priceLevel: Int?
    let rating: Double?
    let openStatus: String?
    let latitude: Double
    let longitude: Double

    var id: String { restaurantId.map(String.init) ?? googlePlaceId ?? name }
}

struct PlaceSearchResponseV2: Decodable {
    let results: [PlaceSearchResult]
}

struct ResolvePlaceRequestBody: Encodable {
    let googlePlaceId: String
}

struct ResolvePlaceResponse: Decodable {
    let restaurant: NearbyPlace
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
    let phone: String?
    let instagramHandle: String?
    let tiktokHandle: String?
    let websiteUrl: String?
    let menuItems: [MenuItem]
    let communityPhotos: [String]
}
