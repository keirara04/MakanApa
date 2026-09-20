import Foundation

enum APIConfig {
    // api.makanapa.app isn't provisioned yet (DNS doesn't resolve) — Release/TestFlight
    // builds point at the dev server too until production is live, so beta testers aren't
    // hitting a dead host.
    static let baseURL = URL(string: "https://api-dev.hakeemiridza.com/api/v1")!
}

enum APIClient {
    private static let decoder: JSONDecoder = {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .useDefaultKeys
        return decoder
    }()

    private static let encoder = JSONEncoder()

    static func recommendSolo(
        latitude: Double, longitude: Double, budgetMax: Int?, maxDistanceKm: Double,
        moods: [String], craving: String? = nil, mode: DiscoveryMode? = nil, vibe: Vibe? = nil
    ) async throws -> RecommendationResponse {
        let body = SoloRecommendationRequestBody(
            latitude: latitude, longitude: longitude, budgetMax: budgetMax,
            maxDistanceKm: maxDistanceKm, moods: moods, craving: craving,
            mode: mode, vibe: vibe, installationId: InstallationID.current
        )
        return try await post("recommendations/solo", body: body)
    }

    static func reroll(decisionId: Int, clientToken: String) async throws -> RerollResponse {
        try await post("decisions/\(decisionId)/reroll", body: EmptyBody(), clientToken: clientToken)
    }

    static func accept(decisionId: Int, clientToken: String) async throws -> AcceptResponse {
        try await post("decisions/\(decisionId)/accept", body: EmptyBody(), clientToken: clientToken)
    }

    static func nearbyPlaces(
        viewport: MapViewport, openNow: Bool?, budgetMax: Int?, minRating: Double?,
        mode: DiscoveryMode? = nil, vibe: Vibe? = nil
    ) async throws -> NearbyPlacesResponse {
        var query: [URLQueryItem] = [
            URLQueryItem(name: "north", value: String(viewport.north)),
            URLQueryItem(name: "south", value: String(viewport.south)),
            URLQueryItem(name: "east", value: String(viewport.east)),
            URLQueryItem(name: "west", value: String(viewport.west)),
        ]
        if let openNow { query.append(URLQueryItem(name: "openNow", value: openNow ? "1" : "0")) }
        if let budgetMax { query.append(URLQueryItem(name: "budgetMax", value: String(budgetMax))) }
        if let minRating { query.append(URLQueryItem(name: "minRating", value: String(minRating))) }
        if let mode { query.append(URLQueryItem(name: "mode", value: mode.rawValue)) }
        if let vibe { query.append(URLQueryItem(name: "vibe", value: vibe.rawValue)) }
        return try await get("places/nearby", query: query)
    }

    static func placeDetails(restaurantId: Int) async throws -> PlaceDetails {
        try await get("restaurants/\(restaurantId)/details", query: [])
    }

    static func pickFromVisible(
        latitude: Double, longitude: Double, viewport: MapViewport, visiblePlaceIds: [Int],
        openNow: Bool?, budgetMax: Int?, minRating: Double?, mode: DiscoveryMode? = nil, vibe: Vibe? = nil
    ) async throws -> NearbyPickResponse {
        let body = NearbyPickRequestBody(
            latitude: latitude, longitude: longitude, viewport: viewport, visiblePlaceIds: visiblePlaceIds,
            openNow: openNow, budgetMax: budgetMax, minRating: minRating,
            mode: mode, vibe: vibe, installationId: InstallationID.current
        )
        return try await post("places/nearby/pick", body: body)
    }

    static func saveRestaurant(id: Int) async throws -> SaveResponse {
        try await post("restaurants/\(id)/save", body: SaveRequestBody(installationId: InstallationID.current))
    }

    static func unsaveRestaurant(id: Int) async throws -> SaveResponse {
        try await post("restaurants/\(id)/unsave", body: SaveRequestBody(installationId: InstallationID.current))
    }

    static func submitVibeTag(decisionId: Int, clientToken: String, vibe: CommunityTag) async throws -> VibeTagResponse {
        try await post("decisions/\(decisionId)/vibe-tag", body: VibeTagRequestBody(vibe: vibe), clientToken: clientToken)
    }

    // MARK: - Auth

    static func login(email: String, password: String, deviceLabel: String) async throws -> LoginResponse {
        try await post("auth/login", body: LoginRequestBody(email: email, password: password, deviceLabel: deviceLabel), authenticated: false)
    }

    static func logout() async throws -> LogoutResponse {
        try await post("auth/logout", body: EmptyBody())
    }

    static func me() async throws -> MeResponse {
        try await get("auth/me", query: [])
    }

    // MARK: - Admin

    static func listBetaUsers() async throws -> AdminUserListResponse {
        try await get("admin/users", query: [])
    }

    static func createBetaUser(email: String, university: String?) async throws -> CreateBetaUserResponse {
        try await post("admin/users", body: CreateBetaUserRequestBody(email: email, university: university))
    }

    static func revokeBetaUser(id: Int) async throws -> RevokeUserResponse {
        try await post("admin/users/\(id)/revoke", body: EmptyBody())
    }

    static func listUniversities() async throws -> UniversitiesResponse {
        try await get("universities", query: [])
    }

    static func communityFeed(latitude: Double?, longitude: Double?) async throws -> CommunityFeedResponse {
        var query: [URLQueryItem] = []
        if let latitude { query.append(URLQueryItem(name: "latitude", value: String(latitude))) }
        if let longitude { query.append(URLQueryItem(name: "longitude", value: String(longitude))) }
        return try await get("community/feed", query: query)
    }

    // MARK: - Community places (submissions)

    static func searchCommunityPlaces(query: String, latitude: Double?, longitude: Double?) async throws -> PlaceSearchResponse {
        var items: [URLQueryItem] = [URLQueryItem(name: "query", value: query)]
        if let latitude { items.append(URLQueryItem(name: "latitude", value: String(latitude))) }
        if let longitude { items.append(URLQueryItem(name: "longitude", value: String(longitude))) }
        return try await get("community/places/search", query: items)
    }

    static func createSubmission(_ body: CreateSubmissionRequestBody) async throws -> SubmissionResponse {
        try await post("community/submissions", body: body)
    }

    static func mySubmissions() async throws -> MySubmissionsResponse {
        try await get("community/submissions/mine", query: [])
    }

    static func updateSubmission(id: Int, _ body: UpdateSubmissionRequestBody) async throws -> SubmissionResponse {
        try await patch("community/submissions/\(id)", body: body)
    }

    static func cancelSubmission(id: Int) async throws -> CancelSubmissionResponse {
        try await delete("community/submissions/\(id)")
    }

    // MARK: - Admin: community places moderation

    static func adminListSubmissions(status: String) async throws -> AdminSubmissionListResponse {
        try await get("admin/community/submissions", query: [URLQueryItem(name: "status", value: status)])
    }

    static func adminApproveSubmission(id: Int) async throws -> AdminApproveResponse {
        try await post("admin/community/submissions/\(id)/approve", body: EmptyBody())
    }

    static func adminLinkSubmission(id: Int, restaurantId: Int) async throws -> AdminLinkResponse {
        try await post("admin/community/submissions/\(id)/link", body: LinkSubmissionRequestBody(restaurantId: restaurantId))
    }

    static func adminRejectSubmission(id: Int, reviewNote: String) async throws -> AdminRejectResponse {
        try await post("admin/community/submissions/\(id)/reject", body: ReviewNoteRequestBody(reviewNote: reviewNote))
    }

    static func adminRequestChanges(id: Int, reviewNote: String) async throws -> AdminRequestChangesResponse {
        try await post("admin/community/submissions/\(id)/request-changes", body: ReviewNoteRequestBody(reviewNote: reviewNote))
    }

    private struct EmptyBody: Encodable {}

    private static func patch<Body: Encodable, Response: Decodable>(_ path: String, body: Body) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "PATCH"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        await attachAuthorization(to: &request)
        request.httpBody = try encoder.encode(body)

        let (data, httpResponse) = try await send(request)
        try validate(httpResponse)

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    private static func delete<Response: Decodable>(_ path: String) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "DELETE"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        await attachAuthorization(to: &request)

        let (data, httpResponse) = try await send(request)
        try validate(httpResponse)

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    private static func get<Response: Decodable>(_ path: String, query: [URLQueryItem]) async throws -> Response {
        var components = URLComponents(url: APIConfig.baseURL.appendingPathComponent(path), resolvingAgainstBaseURL: false)
        components?.queryItems = query
        guard let url = components?.url else { throw APIError.invalidResponse }

        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        await attachAuthorization(to: &request)

        let (data, httpResponse) = try await send(request)
        try validate(httpResponse)

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    private static func post<Body: Encodable, Response: Decodable>(
        _ path: String, body: Body, clientToken: String? = nil, authenticated: Bool = true
    ) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let clientToken {
            request.setValue(clientToken, forHTTPHeaderField: "X-Decision-Token")
        }
        if authenticated {
            await attachAuthorization(to: &request)
        }
        request.httpBody = try encoder.encode(body)

        let (data, httpResponse) = try await send(request)
        try validate(httpResponse)

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    @MainActor
    private static func attachAuthorization(to request: inout URLRequest) {
        if let token = CredentialStore.shared.token {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
    }

    private static func send(_ request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await URLSession.shared.data(for: request)
        } catch {
            throw APIError.transport(error)
        }
        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }
        #if DEBUG
        let authHeader = request.value(forHTTPHeaderField: "Authorization")
        print("[APIClient] \(request.httpMethod ?? "?") \(request.url?.path ?? "?") -> \(httpResponse.statusCode) | Authorization sent: \(authHeader != nil ? String(authHeader!.prefix(20)) + "…" : "NONE")")
        if !(200..<300).contains(httpResponse.statusCode) {
            print("[APIClient] body: \(String(data: data, encoding: .utf8) ?? "?")")
        }
        #endif
        return (data, httpResponse)
    }

    /// Surfaces a 401 as one common error so callers can react in one place (see
    /// `AuthStore.handleUnauthorized`) instead of every call site checking status codes itself.
    private static func validate(_ response: HTTPURLResponse) throws {
        if response.statusCode == 401 {
            throw APIError.unauthorized
        }
        guard (200..<300).contains(response.statusCode) else {
            throw APIError.server(statusCode: response.statusCode)
        }
    }
}
