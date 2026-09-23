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
        moods: [String], craving: String? = nil, mode: DiscoveryMode? = nil, vibe: Vibe? = nil, lens: Lens? = nil
    ) async throws -> RecommendationResponse {
        let body = SoloRecommendationRequestBody(
            latitude: latitude, longitude: longitude, budgetMax: budgetMax,
            maxDistanceKm: maxDistanceKm, moods: moods, craving: craving,
            mode: mode, vibe: vibe, installationId: InstallationID.current,
            halal: HalalPreference.isOn,
            lens: lens, ignoreContext: ContextPreferences.ignoredKeys
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
        query.append(URLQueryItem(name: "halal", value: HalalPreference.isOn ? "1" : "0"))
        return try await get("places/nearby", query: query)
    }

    static func placeDetails(restaurantId: Int) async throws -> PlaceDetails {
        try await get("restaurants/\(restaurantId)/details", query: [])
    }

    static func searchPlaces(query: String, latitude: Double, longitude: Double) async throws -> PlaceSearchResponseV2 {
        let items: [URLQueryItem] = [
            URLQueryItem(name: "query", value: query),
            URLQueryItem(name: "latitude", value: String(latitude)),
            URLQueryItem(name: "longitude", value: String(longitude)),
        ]
        return try await get("places/search", query: items)
    }

    static func resolvePlace(googlePlaceId: String) async throws -> ResolvePlaceResponse {
        try await post("places/resolve", body: ResolvePlaceRequestBody(googlePlaceId: googlePlaceId))
    }

    static func pickFromVisible(
        latitude: Double, longitude: Double, viewport: MapViewport, visiblePlaceIds: [Int],
        openNow: Bool?, budgetMax: Int?, minRating: Double?, mode: DiscoveryMode? = nil, vibe: Vibe? = nil
    ) async throws -> NearbyPickResponse {
        let body = NearbyPickRequestBody(
            latitude: latitude, longitude: longitude, viewport: viewport, visiblePlaceIds: visiblePlaceIds,
            openNow: openNow, budgetMax: budgetMax, minRating: minRating,
            mode: mode, vibe: vibe, installationId: InstallationID.current,
            halal: HalalPreference.isOn,
            ignoreContext: ContextPreferences.ignoredKeys
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

    // MARK: - Makan Brain

    static func context(latitude: Double, longitude: Double) async throws -> ContextResponse {
        var query = [
            URLQueryItem(name: "latitude", value: String(latitude)),
            URLQueryItem(name: "longitude", value: String(longitude)),
        ]
        for key in ContextPreferences.ignoredKeys {
            query.append(URLQueryItem(name: "ignore[]", value: key))
        }
        return try await get("context", query: query)
    }

    static func tune(decisionId: Int, clientToken: String, direction: TuneDirection) async throws -> TuneResponse {
        try await post("decisions/\(decisionId)/tune", body: TuneRequestBody(direction: direction), clientToken: clientToken)
    }

    static func whatIf(decisionId: Int, clientToken: String) async throws -> WhatIfResponse {
        try await get("decisions/\(decisionId)/what-if", query: [], clientToken: clientToken)
    }

    static func choose(decisionId: Int, clientToken: String, restaurantId: Int) async throws -> RerollResponse {
        try await post("decisions/\(decisionId)/choose", body: ChooseRequestBody(restaurantId: restaurantId), clientToken: clientToken)
    }

    static func whyNot(decisionId: Int, clientToken: String, reason: WhyNotReason, detail: WhyNotDetail?) async throws -> RecordedResponse {
        try await post("decisions/\(decisionId)/why-not", body: WhyNotRequestBody(reason: reason, detail: detail), clientToken: clientToken)
    }

    static func logInteraction(decisionId: Int, clientToken: String, type: String) async throws -> RecordedResponse {
        try await post("decisions/\(decisionId)/interactions", body: InteractionRequestBody(type: type), clientToken: clientToken)
    }

    static func selera() async throws -> SeleraResponse {
        try await get("me/selera", query: [])
    }

    static func seleraTraitFeedback(key: String, kind: String) async throws -> SeleraResponse {
        try await post("me/selera/traits/\(key)/feedback", body: SeleraFeedbackRequestBody(kind: kind))
    }

    static func muteSeleraTrait(key: String) async throws -> SeleraResponse {
        try await delete("me/selera/traits/\(key)")
    }

    static func resetSelera() async throws -> SeleraResponse {
        try await post("me/selera/reset", body: EmptyBody())
    }

    // MARK: - Auth

    static func login(email: String, password: String, deviceLabel: String) async throws -> LoginResponse {
        try await post("auth/login", body: LoginRequestBody(email: email, password: password, deviceLabel: deviceLabel), authenticated: false)
    }

    static func register(name: String, email: String, password: String, deviceLabel: String) async throws -> LoginResponse {
        try await post("auth/register", body: RegisterRequestBody(name: name, email: email, password: password, deviceLabel: deviceLabel), authenticated: false)
    }

    static func loginWithApple(
        identityToken: String, authorizationCode: String, nonce: String, fullName: String?, deviceLabel: String
    ) async throws -> SocialLoginResponse {
        try await post(
            "auth/apple",
            body: AppleLoginRequestBody(identityToken: identityToken, authorizationCode: authorizationCode, nonce: nonce, fullName: fullName, deviceLabel: deviceLabel),
            authenticated: false
        )
    }

    static func loginWithGoogle(idToken: String, deviceLabel: String) async throws -> SocialLoginResponse {
        try await post("auth/google", body: GoogleLoginRequestBody(idToken: idToken, deviceLabel: deviceLabel), authenticated: false)
    }

    static func completeLink(password: String, linkToken: String, deviceLabel: String) async throws -> LoginResponse {
        try await post("auth/link", body: LinkAccountRequestBody(password: password, linkToken: linkToken, deviceLabel: deviceLabel), authenticated: false)
    }

    static func logout() async throws -> LogoutResponse {
        try await post("auth/logout", body: EmptyBody())
    }

    static func startAppSession() async throws -> AppSessionStartResponse {
        try await post("app-sessions/start", body: AppSessionStartRequestBody(installationId: InstallationID.current))
    }

    static func endAppSession(sessionId: Int) async throws -> AppSessionEndResponse {
        try await post("app-sessions/\(sessionId)/end", body: EmptyBody())
    }

    static func deleteAccount(password: String?) async throws -> DeleteAccountResponse {
        try await delete("auth/me", body: DeleteAccountRequestBody(password: password))
    }

    static func me() async throws -> MeResponse {
        try await get("auth/me", query: [])
    }

    static func updateMyCommunity(university: String?, area: String?) async throws -> MeResponse {
        try await patch("me/community", body: UpdateMyCommunityRequestBody(university: university, area: area))
    }

    static func updateMyProfile(name: String?, avatarKey: String?) async throws -> MeResponse {
        try await patch("me/profile", body: UpdateMyProfileRequestBody(name: name, avatarKey: avatarKey))
    }

    static func updateHalalPreference(_ isOn: Bool) async throws -> MeResponse {
        try await patch("me/profile", body: UpdateHalalPreferenceRequestBody(halalPreference: isOn))
    }

    // MARK: - Halal trust

    /// Opens (or returns the caller's existing open) halal report draft. Evidence photos go
    /// through `uploadSubmissionPhoto`, then `submitSubmission` sends it for review.
    static func createHalalReport(restaurantId: Int, _ body: CreateHalalReportRequestBody) async throws -> HalalReportResponse {
        try await post("restaurants/\(restaurantId)/halal-reports", body: body)
    }

    static func createOwnerClaim(restaurantId: Int, _ body: CreateOwnerClaimRequestBody) async throws -> OwnerClaimResponse {
        try await post("restaurants/\(restaurantId)/owner-claim", body: body)
    }

    static func halalHistory(restaurantId: Int, page: Int = 1) async throws -> HalalHistoryResponse {
        try await get("restaurants/\(restaurantId)/halal/history", query: [URLQueryItem(name: "page", value: String(page))])
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

    static func listAreas() async throws -> AreasResponse {
        try await get("areas", query: [])
    }

    static func submitCommunityRequest(type: String, name: String) async throws -> CommunityRequestResponse {
        try await post("community/requests", body: CommunityRequestBody(type: type, name: name))
    }

    static func communityFeed(latitude: Double?, longitude: Double?) async throws -> CommunityFeedResponse {
        var query: [URLQueryItem] = []
        if let latitude { query.append(URLQueryItem(name: "latitude", value: String(latitude))) }
        if let longitude { query.append(URLQueryItem(name: "longitude", value: String(longitude))) }
        return try await get("community/feed", query: query)
    }

    // MARK: - Community posts

    static func communityPosts(restaurantId: Int? = nil, cursor: String? = nil, limit: Int? = nil) async throws -> CommunityPostsResponse {
        var query: [URLQueryItem] = []
        if let restaurantId { query.append(URLQueryItem(name: "restaurantId", value: String(restaurantId))) }
        if let cursor { query.append(URLQueryItem(name: "cursor", value: cursor)) }
        if let limit { query.append(URLQueryItem(name: "limit", value: String(limit))) }
        return try await get("community/posts", query: query)
    }

    static func communityThread(postId: Int, cursor: String? = nil) async throws -> CommunityThreadResponse {
        var query: [URLQueryItem] = []
        if let cursor { query.append(URLQueryItem(name: "cursor", value: cursor)) }
        return try await get("community/posts/\(postId)", query: query)
    }

    static func createCommunityPost(body: String, restaurantId: Int?, parentId: Int?) async throws -> CommunityPostResponse {
        try await sendSurfacingMessage(
            "POST", "community/posts",
            body: CreateCommunityPostRequestBody(body: body, restaurantId: restaurantId, parentId: parentId)
        )
    }

    static func deleteCommunityPost(id: Int) async throws -> CommunityDeletePostResponse {
        try await delete("community/posts/\(id)")
    }

    static func reactToCommunityPost(id: Int, type: CommunityReactionType) async throws -> CommunityReactionResponse {
        try await post("community/posts/\(id)/react", body: CommunityReactionRequestBody(type: type))
    }

    static func reportCommunityPost(id: Int, reason: CommunityReportReason, note: String?) async throws -> CommunityReportResponse {
        try await sendSurfacingMessage("POST", "community/posts/\(id)/report", body: CommunityReportRequestBody(reason: reason, note: note))
    }

    static func blockUser(id: Int) async throws -> BlockUserResponse {
        try await post("users/\(id)/block", body: EmptyBody())
    }

    static func unblockUser(id: Int) async throws -> BlockUserResponse {
        try await delete("users/\(id)/block")
    }

    static func blockedUsers() async throws -> BlockedUsersResponse {
        try await get("me/blocks", query: [])
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

    static func submitSubmission(id: Int) async throws -> SubmitSubmissionResponse {
        try await post("community/submissions/\(id)/submit", body: EmptyBody())
    }

    static func uploadSubmissionPhoto(submissionId: Int, jpegData: Data, photoType: String) async throws -> UploadPhotoResponse {
        try await uploadMultipart(
            "community/submissions/\(submissionId)/photos",
            fileFieldName: "photo", fileName: "photo.jpg", mimeType: "image/jpeg", fileData: jpegData,
            fields: ["photoType": photoType]
        )
    }

    static func quickAddRestaurantPhoto(restaurantId: Int, jpegData: Data, photoType: String) async throws -> UploadPhotoResponse {
        try await uploadMultipart(
            "restaurants/\(restaurantId)/photos/quick-add",
            fileFieldName: "photo", fileName: "photo.jpg", mimeType: "image/jpeg", fileData: jpegData,
            fields: ["photoType": photoType]
        )
    }

    // MARK: - Admin: community places moderation

    static func adminListSubmissions(status: String) async throws -> AdminSubmissionListResponse {
        try await get("admin/community/submissions", query: [URLQueryItem(name: "status", value: status)])
    }

    static func adminApproveSubmission(id: Int) async throws -> AdminApproveResponse {
        try await post("admin/community/submissions/\(id)/approve", body: EmptyBody())
    }

    static func adminApproveHalalReport(id: Int, _ body: AdminApproveHalalRequestBody) async throws -> AdminApproveResponse {
        try await post("admin/community/submissions/\(id)/approve", body: body)
    }

    static func adminListHalalQueue() async throws -> AdminSubmissionListResponse {
        try await get("admin/community/submissions", query: [
            URLQueryItem(name: "status", value: "pending"),
            URLQueryItem(name: "type", value: "halal_report"),
        ])
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

    static func adminSubmissionPhotos(id: Int) async throws -> AdminSubmissionPhotosResponse {
        try await get("admin/community/submissions/\(id)/photos", query: [])
    }

    static func adminReleaseFieldOverride(restaurantId: Int, field: String) async throws -> ReleaseFieldOverrideResponse {
        try await delete("admin/community/restaurants/\(restaurantId)/field-overrides/\(field)")
    }

    static func adminRemoveRestaurant(restaurantId: Int) async throws -> AdminRemoveResponse {
        try await post("admin/community/restaurants/\(restaurantId)/remove", body: EmptyBody())
    }

    // MARK: - Admin: community requests + university/area management

    static func adminListCommunityRequests(status: String) async throws -> AdminCommunityRequestListResponse {
        try await get("admin/community/requests", query: [URLQueryItem(name: "status", value: status)])
    }

    static func adminResolveCommunityRequest(id: Int) async throws -> AdminCommunityRequestResolveResponse {
        try await post("admin/community/requests/\(id)/resolve", body: EmptyBody())
    }

    static func adminDismissCommunityRequest(id: Int) async throws -> AdminCommunityRequestDismissResponse {
        try await post("admin/community/requests/\(id)/dismiss", body: EmptyBody())
    }

    static func adminListUniversities() async throws -> AdminUniversityListResponse {
        try await get("admin/universities", query: [])
    }

    static func adminCreateUniversity(name: String, shortName: String) async throws -> CreateUniversityResponse {
        try await post("admin/universities", body: CreateUniversityRequestBody(name: name, shortName: shortName))
    }

    static func adminListAreas() async throws -> AdminAreaListResponse {
        try await get("admin/areas", query: [])
    }

    static func adminCreateArea(name: String, shortName: String) async throws -> CreateAreaResponse {
        try await post("admin/areas", body: CreateAreaRequestBody(name: name, shortName: shortName))
    }

    // MARK: - Push notifications

    static func registerDeviceToken(installationId: String, token: String, environment: String) async throws -> DeviceTokenAckResponse {
        try await post(
            "device-tokens",
            body: RegisterDeviceTokenRequestBody(installationId: installationId, token: token, environment: environment),
            authenticated: false
        )
    }

    static func claimDeviceToken(installationId: String, token: String, environment: String) async throws -> DeviceTokenAckResponse {
        try await post(
            "me/device-tokens/claim",
            body: RegisterDeviceTokenRequestBody(installationId: installationId, token: token, environment: environment)
        )
    }

    static func unclaimDeviceToken(installationId: String, environment: String) async throws -> DeviceTokenAckResponse {
        try await delete("me/device-tokens/claim", body: UnclaimDeviceTokenRequestBody(installationId: installationId, environment: environment))
    }

    static func fetchNotificationPreferences() async throws -> NotificationPreferencesResponse {
        try await get("me/notification-preferences", query: [])
    }

    static func updateNotificationPreferences(_ body: UpdateNotificationPreferencesRequestBody) async throws -> NotificationPreferencesResponse {
        try await patch("me/notification-preferences", body: body)
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

    private static func delete<Body: Encodable, Response: Decodable>(_ path: String, body: Body) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "DELETE"
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

    private static func get<Response: Decodable>(_ path: String, query: [URLQueryItem], clientToken: String? = nil) async throws -> Response {
        var components = URLComponents(url: APIConfig.baseURL.appendingPathComponent(path), resolvingAgainstBaseURL: false)
        components?.queryItems = query.isEmpty ? nil : query
        guard let url = components?.url else { throw APIError.invalidResponse }

        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let clientToken {
            request.setValue(clientToken, forHTTPHeaderField: "X-Decision-Token")
        }
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

    private struct ServerErrorBody: Decodable {
        let message: String?
        let errors: [String: [String]]?
    }

    /// Like `post`, but a 4xx with a readable body becomes `APIError.rejected` carrying the
    /// server's message — for writes whose rejection the user needs to understand (e.g. the
    /// community content filter: "Links aren't allowed…"), not just a generic failure.
    private static func sendSurfacingMessage<Body: Encodable, Response: Decodable>(
        _ method: String, _ path: String, body: Body
    ) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = method
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        await attachAuthorization(to: &request)
        request.httpBody = try encoder.encode(body)

        let (data, httpResponse) = try await send(request)
        if (400..<500).contains(httpResponse.statusCode), httpResponse.statusCode != 401,
           let errorBody = try? decoder.decode(ServerErrorBody.self, from: data),
           let message = errorBody.errors?.values.first?.first ?? errorBody.message, !message.isEmpty {
            throw APIError.rejected(statusCode: httpResponse.statusCode, message: message)
        }
        try validate(httpResponse)

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    private static func uploadMultipart<Response: Decodable>(
        _ path: String, fileFieldName: String, fileName: String, mimeType: String, fileData: Data, fields: [String: String]
    ) async throws -> Response {
        let boundary = "Boundary-\(UUID().uuidString)"
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.timeoutInterval = 30
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        await attachAuthorization(to: &request)

        var body = Data()
        for (key, value) in fields {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(key)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"\(fileFieldName)\"; filename=\"\(fileName)\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: \(mimeType)\r\n\r\n".data(using: .utf8)!)
        body.append(fileData)
        body.append("\r\n--\(boundary)--\r\n".data(using: .utf8)!)
        request.httpBody = body

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
