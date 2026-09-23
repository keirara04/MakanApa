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

struct RegisterRequestBody: Encodable {
    let name: String
    let email: String
    let password: String
    let deviceLabel: String
}

struct AppleLoginRequestBody: Encodable {
    let identityToken: String
    let authorizationCode: String
    let nonce: String
    let fullName: String?
    let deviceLabel: String
}

struct GoogleLoginRequestBody: Encodable {
    let idToken: String
    let deviceLabel: String
}

struct LinkAccountRequestBody: Encodable {
    let password: String
    let linkToken: String
    let deviceLabel: String
}

struct DeleteAccountRequestBody: Encodable {
    let password: String?
}

struct DeleteAccountResponse: Decodable {
    let deleted: Bool
}

/// Covers both outcomes of /auth/apple and /auth/google in one decodable shape — an immediate
/// session (`token`/`user` present) or a pending link offer (`needsLinking`/`linkToken`/`email`
/// present instead). See AuthStore.SocialLoginOutcome for the branch callers actually use.
struct SocialLoginResponse: Decodable {
    let token: String?
    let user: AuthUser?
    let needsLinking: Bool?
    let linkToken: String?
    let email: String?
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
    let area: String?
}

/// Custom `encode(to:)` because the synthesized one omits `nil` keys entirely — this call site
/// always sends both fields as a full snapshot, and `avatarKey: nil` must serialize as an
/// explicit JSON `null` (backend resets the avatar) rather than being dropped (backend would
/// leave it unchanged).
struct UpdateMyProfileRequestBody: Encodable {
    let name: String?
    let avatarKey: String?

    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(name, forKey: .name)
        try container.encode(avatarKey, forKey: .avatarKey)
    }

    private enum CodingKeys: String, CodingKey {
        case name, avatarKey
    }
}

struct UniversityOption: Decodable, Identifiable, Equatable {
    let shortName: String
    let name: String

    var id: String { shortName }
}

struct UniversitiesResponse: Decodable {
    let universities: [UniversityOption]
}

/// Mirrors `UniversityOption` — an admin-curated named group ("KL", "Selangor", a
/// neighbourhood), not geo-bounded, self-selected the same way a university is.
struct AreaOption: Decodable, Identifiable, Equatable {
    let shortName: String
    let name: String

    var id: String { shortName }
}

struct AreasResponse: Decodable {
    let areas: [AreaOption]
}

struct CommunityInfo: Decodable, Equatable {
    let type: String
    let university: String?
    let area: String?
    let label: String
}

// MARK: - Community requests ("my university/area isn't listed")

struct CommunityRequestBody: Encodable {
    let type: String
    let name: String
}

struct CommunityRequestResponse: Decodable {
    let requested: Bool
}

struct CommunityFeedItem: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let foodCategory: String?
    let rating: Double?
    let priceLevel: Int?
    let cuisines: [String]
    let openStatus: String
    let distanceKm: Double?
    // Trending-only — nil for "new in your area" items, which have no pick history yet.
    let pickCount: Int?
    let pickerCount: Int?
    let trendingVibe: String?
    // New-in-area-only — nil for trending items.
    let approvedAt: String?
}

struct CommunityFeedResponse: Decodable, Equatable {
    let community: CommunityInfo
    let trending: [CommunityFeedItem]
    let newInArea: [CommunityFeedItem]
}

// MARK: - Community places (submissions)

struct ExistingPlaceResult: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let address: String?
    let foodCategory: String?
    let priceLevel: Int?
    let distanceKm: Double?
    /// The place's canonical location — an edit can't move it, but the submission still carries it.
    /// Optional so an older backend still decodes.
    var latitude: Double? = nil
    var longitude: Double? = nil
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
    case halalReport = "halal_report"
    case ownerClaim = "owner_claim"
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
    let halalClaim: HalalStatus?
    let halalResolvedStatus: HalalStatus?
    let halalComment: String?
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
    let halal: AdminHalalEvidence?
    let contactPhone: String?
    let createdAt: String
}

/// Admin-only view of a halal report — includes the certificate number the public payload never shows.
struct AdminHalalEvidence: Decodable, Equatable {
    let claim: HalalStatus?
    let comment: String?
    let certificationAuthority: CertificationAuthority?
    let certificateNumber: String?
    let certificateExpiresAt: String?
    let currentStatus: HalalStatus?
    let reviewPriority: Int
    let duplicatePhoto: Bool
    let registryUrl: String?
    /// Advisory AI triage badges ("Mentions certificate 0.92", "Likely spam 0.81") — the admin still decides.
    let aiBadges: [String]?
    /// "base 50, verified owner +20, ai strong evidence +10 → 80"
    let priorityExplanation: String?
}

/// Every detail is optional; `confirmed` is the admin's explicit attestation that the place is certified.
struct AdminHalalCertificateBody: Encodable {
    let confirmed: Bool
    let authority: CertificationAuthority?
    let certificateNumber: String?
    let expiresAt: String?
    let verificationMethod: String
}

struct AdminApproveHalalRequestBody: Encodable {
    let resolvedStatus: HalalStatus
    let evidenceSummary: String?
    let certificate: AdminHalalCertificateBody?
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

struct AdminRemoveResponse: Decodable {
    let removed: Bool
}

// MARK: - Admin: community requests + university/area management

struct AdminCommunityRequest: Decodable, Identifiable, Equatable {
    let id: Int
    let type: String
    let name: String
    let status: String
    let createdAt: String
    let requesterEmail: String?
}

struct AdminCommunityRequestListResponse: Decodable {
    let requests: [AdminCommunityRequest]
}

struct AdminCommunityRequestResolveResponse: Decodable {
    let resolved: Bool
}

struct AdminCommunityRequestDismissResponse: Decodable {
    let dismissed: Bool
}

struct AdminUniversityListItem: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let shortName: String
    let active: Bool
}

struct AdminUniversityListResponse: Decodable {
    let universities: [AdminUniversityListItem]
}

struct CreateUniversityRequestBody: Encodable {
    let name: String
    let shortName: String
}

struct CreateUniversityResponse: Decodable {
    let university: UniversityOption
}

struct AdminAreaListItem: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let shortName: String
    let active: Bool
}

struct AdminAreaListResponse: Decodable {
    let areas: [AdminAreaListItem]
}

struct CreateAreaRequestBody: Encodable {
    let name: String
    let shortName: String
}

struct CreateAreaResponse: Decodable {
    let area: AreaOption
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
    let halal: Bool
    /// Makan Brain: explicit "how do I want to decide" lens + context signals the user switched off.
    var lens: Lens? = nil
    var ignoreContext: [String]? = nil
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
        let menuItems: [MenuItem]
        let placeGoogleMapsUrl: String?
        let closesAt: String?
        /// Only present once a restaurant has enough CommunityTag votes to clear the backend's
        /// confidence threshold (PresentsRecommendation::communityTagBadge()) — absent, not a
        /// low-confidence guess, below that bar.
        let communityTag: CommunityTag?
        /// Optional so an older backend (or a decode of a cached response) never breaks the result screen.
        let halal: HalalInfo?

        // Makan Brain (algorithmVersion v2) — all optional: absent on v1 decisions / older backends.
        let reasons: [PickReason]?
        let decidingFactor: String?
        let fit: PickFit?
        let thinkingTrace: [String]?
        let context: [ContextSignal]?
        let hasWhatIf: Bool?
        let canTune: Bool?
        let fatigue: Bool?
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

struct MapViewport: Encodable, Equatable {
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
    let halal: HalalSummary?
}

struct NearbyPlacesResponse: Decodable {
    let places: [NearbyPlace]
    let areaSummary: AreaSummaryResponse
}

struct AreaCategoryCount: Decodable, Equatable {
    let label: String
    let count: Int
}

/// "budget_friendly" | "category_heavy" — plain data from the backend, never emoji/prose;
/// this layer decides how each key actually renders.
struct AreaPersonalityTag: Decodable, Equatable, Identifiable {
    let key: String
    let label: String

    var id: String { key }
}

/// Nearby's "what's around here" interpretation layer — computed backend-side from the exact
/// same viewport-and-filter-scoped restaurant set the marker list itself uses, never a second,
/// independently fetched dataset (see `NearbyController::buildAreaSummary`).
struct AreaSummaryResponse: Decodable, Equatable {
    let placeCount: Int
    let openNowCount: Int
    let budgetFriendlyCount: Int
    let topCategories: [AreaCategoryCount]
    let topRated: [NearbyPlace]
    let communityFinds: [NearbyPlace]
    let personalityTags: [AreaPersonalityTag]
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
    /// Short street/area address ("Jalan Reko, Kajang") — what tells branches apart. Nil for
    /// rows synced before addresses were stored.
    let address: String?
    let category: String?
    let cuisine: String?
    let distanceKm: Double?
    let priceLevel: Int?
    let rating: Double?
    let openStatus: String
    /// "10:00 PM" local, only when open now and weekly hours are known.
    let closesAt: String?
    let halal: HalalSummary?
    let isCommunityFind: Bool
    /// Shared by likely branches of one chain; nil when the row stands alone.
    let groupKey: String?
    let groupSize: Int
    let latitude: Double
    let longitude: Double

    var id: String { restaurantId.map(String.init) ?? googlePlaceId ?? name }

    private enum CodingKeys: String, CodingKey {
        case provenance, id, restaurantId, googlePlaceId, name, address, category, cuisine, distanceKm
        case priceLevel, rating, openStatus, closesAt, halal, isCommunityFind, groupKey, groupSize
        case latitude, longitude
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        provenance = try c.decode(PlaceSearchProvenance.self, forKey: .provenance)
        // `id` is the canonical key; `restaurantId` is what older backends sent.
        restaurantId = try c.decodeIfPresent(Int.self, forKey: .id) ?? c.decodeIfPresent(Int.self, forKey: .restaurantId)
        googlePlaceId = try c.decodeIfPresent(String.self, forKey: .googlePlaceId)
        name = try c.decode(String.self, forKey: .name)
        address = try c.decodeIfPresent(String.self, forKey: .address)
        category = try c.decodeIfPresent(String.self, forKey: .category)
        cuisine = try c.decodeIfPresent(String.self, forKey: .cuisine)
        distanceKm = try c.decodeIfPresent(Double.self, forKey: .distanceKm)
        priceLevel = try c.decodeIfPresent(Int.self, forKey: .priceLevel)
        rating = try c.decodeIfPresent(Double.self, forKey: .rating)
        openStatus = try c.decodeIfPresent(String.self, forKey: .openStatus) ?? "unknown"
        closesAt = try c.decodeIfPresent(String.self, forKey: .closesAt)
        halal = try? c.decodeIfPresent(HalalSummary.self, forKey: .halal)
        isCommunityFind = try c.decodeIfPresent(Bool.self, forKey: .isCommunityFind) ?? (provenance == .community)
        groupKey = try c.decodeIfPresent(String.self, forKey: .groupKey)
        groupSize = try c.decodeIfPresent(Int.self, forKey: .groupSize) ?? 1
        latitude = try c.decode(Double.self, forKey: .latitude)
        longitude = try c.decode(Double.self, forKey: .longitude)
    }

    /// The canonical marker/sheet shape — so the sheet's first paint already has the right open
    /// status and halal badge instead of "unknown" until details load.
    func asNearbyPlace(id: Int) -> NearbyPlace {
        NearbyPlace(
            id: id, name: name, rating: rating, priceLevel: priceLevel,
            latitude: latitude, longitude: longitude, openStatus: openStatus, halal: halal
        )
    }
}

struct PlaceSearchMeta: Decodable, Equatable {
    let radiusKm: Double
    /// local | google | mixed
    let source: String
    /// Google wasn't asked this time but could be — the list offers "Search Google".
    let googleAvailable: Bool
    /// The next "Search wider" radius, nil once at the widest.
    let widerRadiusKm: Double?
    let total: Int
}

struct PlaceSearchResponseV2: Decodable {
    let results: [PlaceSearchResult]
    /// Optional: older backends didn't send it.
    let meta: PlaceSearchMeta?
    let suggestions: [String]?
}

struct ChooseRestaurantRequestBody: Encodable {
    struct SearchContext: Encodable {
        let query: String
        let radiusKm: Double
        let source: String
    }

    let clientChoiceId: String
    let installationId: String
    let latitude: Double?
    let longitude: Double?
    let search: SearchContext?
}

struct ChooseRestaurantResponse: Decodable {
    let decisionId: Int
    let clientToken: String
    let created: Bool
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
    let halal: Bool
    /// Makan Brain: explicit "how do I want to decide" lens + context signals the user switched off.
    var lens: Lens? = nil
    var ignoreContext: [String]? = nil
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
    let halal: HalalInfo?
}

struct AppSessionStartRequestBody: Encodable {
    let installationId: String?
}

struct AppSessionStartResponse: Decodable {
    let sessionId: Int
}

struct AppSessionEndResponse: Decodable {
    let ended: Bool
}

// MARK: - Push notifications

struct RegisterDeviceTokenRequestBody: Encodable {
    let installationId: String
    let token: String
    let environment: String
}

struct DeviceTokenAckResponse: Decodable {
    let ok: Bool
}

struct UnclaimDeviceTokenRequestBody: Encodable {
    let installationId: String
    let environment: String
}

/// Mirrors the backend's `NotificationCategory` keys — v1 categories only (nearby/marketing
/// re-engagement is deferred, see the push notifications plan). Backend keys are snake_case
/// (matches `App\Support\NotificationCategory`), unlike every other endpoint in this file, so
/// this one needs explicit CodingKeys rather than relying on the shared camelCase default.
struct NotificationPreferences: Codable, Equatable {
    var communitySubmissions: Bool
    var accountAdmin: Bool
    var releaseAnnouncements: Bool
    var communityReplies: Bool
    var communityReactions: Bool

    private enum CodingKeys: String, CodingKey {
        case communitySubmissions = "community_submissions"
        case accountAdmin = "account_admin"
        case releaseAnnouncements = "release_announcements"
        case communityReplies = "community_replies"
        case communityReactions = "community_reactions"
    }

    // decodeIfPresent for the community keys — an older API build that predates community
    // posts omits them, and that must not make the whole Notifications section disappear.
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        communitySubmissions = try container.decode(Bool.self, forKey: .communitySubmissions)
        accountAdmin = try container.decode(Bool.self, forKey: .accountAdmin)
        releaseAnnouncements = try container.decode(Bool.self, forKey: .releaseAnnouncements)
        communityReplies = try container.decodeIfPresent(Bool.self, forKey: .communityReplies) ?? true
        communityReactions = try container.decodeIfPresent(Bool.self, forKey: .communityReactions) ?? false
    }
}

struct NotificationPreferencesResponse: Decodable {
    let preferences: NotificationPreferences
}

struct UpdateNotificationPreferencesRequestBody: Encodable {
    var communitySubmissions: Bool? = nil
    var accountAdmin: Bool? = nil
    var releaseAnnouncements: Bool? = nil
    var communityReplies: Bool? = nil
    var communityReactions: Bool? = nil

    private enum CodingKeys: String, CodingKey {
        case communitySubmissions = "community_submissions"
        case accountAdmin = "account_admin"
        case releaseAnnouncements = "release_announcements"
        case communityReplies = "community_replies"
        case communityReactions = "community_reactions"
    }
}

// MARK: - Halal trust

/// Public halal status. Wording is NEVER derived from this on-device — always render the
/// server's `HalalDisplay` (backend `HalalPresenter` owns the semantics).
enum HalalStatus: String, Codable, CaseIterable, Identifiable {
    case certified
    case muslimFriendly = "muslim_friendly"
    case nonHalal = "non_halal"
    case unknown

    var id: String { rawValue }

    /// Only for the report form's picker and admin UI — not a badge.
    var pickerLabel: String {
        switch self {
        case .certified: "Halal (has certificate)"
        case .muslimFriendly: "Muslim-friendly (no cert)"
        case .nonHalal: "Not halal"
        case .unknown: "Unknown"
        }
    }

    static let claimable: [HalalStatus] = [.certified, .muslimFriendly, .nonHalal]
}

enum CertificationAuthority: String, Codable, CaseIterable, Identifiable {
    case jakim
    case stateIslamicCouncil = "state_islamic_council"
    case muis
    case bpjph
    case other

    var id: String { rawValue }

    var label: String {
        switch self {
        case .jakim: "JAKIM"
        case .stateIslamicCouncil: "State Islamic council (JAIN/MAIN)"
        case .muis: "MUIS"
        case .bpjph: "BPJPH"
        case .other: "Other"
        }
    }
}

struct HalalDisplay: Decodable, Equatable {
    let shortLabel: String
    let longLabel: String
    /// certified | friendly | neutral | warning | non_halal
    let tone: String
    /// help_verify | help_reverify | nil
    let action: String?
    /// Absent on map markers (compact payload).
    let verificationLabel: String?

    var invitesReport: Bool { action != nil }
}

/// Compact marker/list shape.
struct HalalSummary: Decodable, Equatable {
    let status: HalalStatus
    let display: HalalDisplay
}

struct HalalVerificationInfo: Decodable, Equatable {
    let method: String
    let evidenceSource: String
    let authority: CertificationAuthority?
    let verifiedAt: String?
    let expiresAt: String?
    let registryCheckedAt: String?
}

struct HalalReportPhoto: Decodable, Equatable, Identifiable {
    let id: Int
    let url: String
    let photoType: String
}

struct HalalPublicReport: Decodable, Equatable, Identifiable {
    let id: Int
    let claim: HalalStatus?
    let resolvedStatus: HalalStatus?
    let isCurrent: Bool
    let comment: String?
    let userName: String
    let approvedAt: String?
    let photos: [HalalReportPhoto]
}

/// Full detail-sheet shape.
struct HalalInfo: Decodable, Equatable {
    let status: HalalStatus
    /// clear | under_review
    let reviewState: String
    let display: HalalDisplay
    let verification: HalalVerificationInfo?
    let reports: [HalalPublicReport]
    let historyCount: Int
    /// The signed-in user's own latest vouch on this place (open, or decided in the last 30 days).
    let myReport: HalalMyReport?
}

struct HalalMyReport: Decodable, Equatable {
    let id: Int
    /// draft | pending | changes_requested | approved | rejected
    let status: String
    let claim: HalalStatus?
    let reviewNote: String?
}

struct HalalHistoryEntry: Decodable, Equatable, Identifiable {
    let id: Int
    let status: HalalStatus
    let state: String
    let method: String
    let evidenceSource: String
    let authority: CertificationAuthority?
    let summary: String?
    let effectiveFrom: String?
    let effectiveUntil: String?
}

struct HalalHistoryResponse: Decodable {
    let entries: [HalalHistoryEntry]
    let nextPage: Int?
}

struct CreateHalalReportRequestBody: Encodable {
    let claim: HalalStatus
    let comment: String?
    let certificationAuthority: CertificationAuthority?
    let certificateNumber: String?
    let certificateExpiresAt: String?
}

struct HalalReportSubmission: Decodable, Equatable {
    let id: Int
    let restaurantId: Int?
    let status: String
    let halalClaim: HalalStatus?
    let halalComment: String?
    let reviewNote: String?
}

struct HalalReportResponse: Decodable {
    let submission: HalalReportSubmission
}

struct CreateOwnerClaimRequestBody: Encodable {
    let contactPhone: String
    let notes: String?
}

struct OwnerClaimResponse: Decodable {
    struct Submission: Decodable { let id: Int; let status: String }
    let submission: Submission
}

struct UpdateHalalPreferenceRequestBody: Encodable {
    let halalPreference: Bool
}

// MARK: - Community posts ("What KU is saying")

enum CommunityReactionType: String, Codable, CaseIterable, Identifiable {
    case up
    case fire
    case drool

    var id: String { rawValue }

    var emoji: String {
        switch self {
        case .up: "👍"
        case .fire: "🔥"
        case .drool: "🤤"
        }
    }

    var accessibilityName: String {
        switch self {
        case .up: "Thumbs up"
        case .fire: "Fire"
        case .drool: "Drooling"
        }
    }
}

enum CommunityReportReason: String, Codable, CaseIterable, Identifiable {
    case spam
    case offensive
    case harassment
    case misleading
    case other

    var id: String { rawValue }

    var label: String {
        switch self {
        case .spam: "Spam or advertising"
        case .offensive: "Offensive or hateful"
        case .harassment: "Harassment or bullying"
        case .misleading: "False or misleading"
        case .other: "Something else"
        }
    }
}

struct CommunityPostAuthor: Decodable, Equatable, Hashable {
    /// nil once the author's account is deleted — there's nobody left to block.
    let id: Int?
    let name: String
    let avatarKey: String?
}

struct CommunityPostPlace: Decodable, Equatable, Hashable {
    let id: Int
    let name: String
    let foodCategory: String?
}

struct CommunityPost: Decodable, Identifiable, Equatable, Hashable {
    let id: Int
    let parentId: Int?
    let body: String
    let createdAt: String
    let author: CommunityPostAuthor
    let isMine: Bool
    let restaurant: CommunityPostPlace?
    var reactionCount: Int
    var reactions: [String: Int]
    var myReaction: CommunityReactionType?
    var replyCount: Int
    /// Top-level posts in a feed page carry their newest replies inline; nil everywhere else.
    var replies: [CommunityPost]?

    var createdDate: Date? {
        CommunityPost.isoFormatter.date(from: createdAt) ?? CommunityPost.isoFormatterNoFraction.date(from: createdAt)
    }

    func count(for reaction: CommunityReactionType) -> Int {
        reactions[reaction.rawValue] ?? 0
    }

    private nonisolated(unsafe) static let isoFormatter: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter
    }()

    private nonisolated(unsafe) static let isoFormatterNoFraction = ISO8601DateFormatter()
}

struct CommunityPostsResponse: Decodable {
    let posts: [CommunityPost]
    let nextCursor: String?
    let canPost: Bool
    let cannotPostReason: String?
}

struct CommunityThreadResponse: Decodable {
    let post: CommunityPost
    let replies: [CommunityPost]
    let nextCursor: String?
    let canReply: Bool
}

struct CreateCommunityPostRequestBody: Encodable {
    let body: String
    let restaurantId: Int?
    let parentId: Int?
}

struct CommunityPostResponse: Decodable {
    let post: CommunityPost
}

struct CommunityReactionRequestBody: Encodable {
    let type: CommunityReactionType
}

struct CommunityReactionResponse: Decodable {
    let myReaction: CommunityReactionType?
    let reactionCount: Int
    let reactions: [String: Int]
}

struct CommunityReportRequestBody: Encodable {
    let reason: CommunityReportReason
    let note: String?
}

struct CommunityReportResponse: Decodable {
    let reported: Bool
}

struct CommunityDeletePostResponse: Decodable {
    let deleted: Bool
}

struct BlockUserResponse: Decodable {
    let blocked: Bool
}

struct BlockedUser: Decodable, Identifiable, Equatable {
    let id: Int
    let name: String
    let avatarKey: String?
}

struct BlockedUsersResponse: Decodable {
    let users: [BlockedUser]
}

// MARK: - Makan Brain

/// Explicit "how do I want to decide today" intent — mirrors App\Support\Lens.
enum Lens: String, Codable, CaseIterable, Identifiable {
    case cheapToday = "cheap_today"
    case treatMyself = "treat_myself"
    case quickOne = "quick_one"
    case surpriseMe = "surprise_me"
    case communityFavs = "community_favs"

    var id: String { rawValue }

    var emoji: String {
        switch self {
        case .cheapToday: "💸"
        case .treatMyself: "✨"
        case .quickOne: "⚡"
        case .surpriseMe: "🎲"
        case .communityFavs: "🔥"
        }
    }

    func label(community: String?) -> String {
        switch self {
        case .cheapToday: "Cheap today"
        case .treatMyself: "Treat myself"
        case .quickOne: "Quick one"
        case .surpriseMe: "Surprise me"
        case .communityFavs: community.map { "\($0) favourites" } ?? "Local favourites"
        }
    }
}

enum PickFit: String, Codable {
    case strong, good, wildcard

    var label: String {
        switch self {
        case .strong: "Strong fit"
        case .good: "Good fit"
        case .wildcard: "Wildcard"
        }
    }
}

struct PickReason: Decodable, Equatable, Hashable {
    /// match | edge | moment — one reason per family at most.
    let family: String
    let key: String
    let icon: String
    let text: String
}

struct ContextSignal: Decodable, Equatable, Hashable, Identifiable {
    let key: String
    let label: String
    let icon: String
    let active: Bool
    let ignored: Bool
    let confidence: Double
    let stale: Bool

    var id: String { key }
}

struct ContextResponse: Decodable {
    let mealSlot: String
    let signals: [ContextSignal]
}

enum TuneDirection: String, Codable, CaseIterable, Identifiable {
    case closer, cheaper, safer, adventurous

    var id: String { rawValue }

    var label: String {
        switch self {
        case .closer: "Closer"
        case .cheaper: "Cheaper"
        case .safer: "Safer bet"
        case .adventurous: "More adventurous"
        }
    }

    var systemImage: String {
        switch self {
        case .closer: "location.fill"
        case .cheaper: "banknote"
        case .safer: "checkmark.shield"
        case .adventurous: "sparkles"
        }
    }
}

struct TuneRequestBody: Encodable {
    let direction: TuneDirection
}

struct SearchWiderAdjustment: Decodable, Equatable {
    let distanceKm: Double
    let budgetMax: Int?
}

struct TuneResponse: Decodable {
    let recommendation: RecommendationResponse.Recommendation?
    let canSearchWider: Bool?
    let message: String?
    let suggestedAdjustment: SearchWiderAdjustment?
}

struct WhatIfEntry: Decodable, Identifiable, Equatable {
    struct Winner: Decodable, Equatable {
        let id: Int
        let name: String?
    }

    let component: String
    let label: String
    let winner: Winner

    var id: String { component }
}

struct WhatIfResponse: Decodable {
    let whatIf: [WhatIfEntry]
}

struct ChooseRequestBody: Encodable {
    let restaurantId: Int
}

enum WhyNotReason: String, Codable, CaseIterable, Identifiable {
    case tooFar = "too_far"
    case tooPricey = "too_pricey"
    case notFeelingIt = "not_feeling_it"
    case ateRecently = "ate_recently"

    var id: String { rawValue }

    var label: String {
        switch self {
        case .tooFar: "Too far"
        case .tooPricey: "Too pricey"
        case .notFeelingIt: "Not feeling it"
        case .ateRecently: "Ate recently"
        }
    }
}

/// Optional second layer under "Not feeling it" — `justNotToday` only nudges today, while
/// `dontLikeCuisine` teaches long-term Selera.
enum WhyNotDetail: String, Codable, CaseIterable, Identifiable {
    case tooHeavy = "too_heavy"
    case tooSimilar = "too_similar"
    case dontLikeCuisine = "dont_like_cuisine"
    case justNotToday = "just_not_today"

    var id: String { rawValue }

    var label: String {
        switch self {
        case .tooHeavy: "Too heavy"
        case .tooSimilar: "Too similar"
        case .dontLikeCuisine: "Don't like this cuisine"
        case .justNotToday: "Just not today"
        }
    }
}

struct WhyNotRequestBody: Encodable {
    let reason: WhyNotReason
    let detail: WhyNotDetail?
}

struct InteractionRequestBody: Encodable {
    let type: String
}

struct RecordedResponse: Decodable {
    let recorded: Bool
}

struct SeleraTrait: Decodable, Identifiable, Equatable {
    let key: String
    let icon: String
    let label: String
    /// emerging | medium | strong
    let strength: String
    let evidence: String

    var id: String { key }
}

struct SeleraConstraint: Decodable, Identifiable, Equatable {
    let key: String
    let label: String
    let icon: String
    let value: Bool?
    /// "settings" | "each_decision"
    let editIn: String

    var id: String { key }
}

struct SeleraResponse: Decodable, Equatable {
    /// starting | learning | knowing | strong
    let stage: String
    let signalCount: Int
    let traits: [SeleraTrait]
    let constraints: [SeleraConstraint]
}

struct SeleraFeedbackRequestBody: Encodable {
    /// not_really | more | less
    let kind: String
}
