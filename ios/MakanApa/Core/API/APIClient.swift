import Foundation

enum APIConfig {
    #if DEBUG
    static let baseURL = URL(string: "https://api-dev.hakeemiridza.com/api/v1")!
    #else
    static let baseURL = URL(string: "https://api.makanapa.app/api/v1")!
    #endif
}

enum APIClient {
    private static let decoder: JSONDecoder = {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .useDefaultKeys
        return decoder
    }()

    private static let encoder = JSONEncoder()

    static func recommendSolo(
        latitude: Double, longitude: Double, budgetMax: Int?, maxDistanceKm: Double, moods: [String]
    ) async throws -> RecommendationResponse {
        let body = SoloRecommendationRequestBody(
            latitude: latitude, longitude: longitude, budgetMax: budgetMax,
            maxDistanceKm: maxDistanceKm, moods: moods
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
        viewport: MapViewport, openNow: Bool?, budgetMax: Int?, minRating: Double?
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
        return try await get("places/nearby", query: query)
    }

    static func placeDetails(restaurantId: Int) async throws -> PlaceDetails {
        try await get("restaurants/\(restaurantId)/details", query: [])
    }

    static func pickFromVisible(
        latitude: Double, longitude: Double, viewport: MapViewport, visiblePlaceIds: [Int],
        openNow: Bool?, budgetMax: Int?, minRating: Double?
    ) async throws -> NearbyPickResponse {
        let body = NearbyPickRequestBody(
            latitude: latitude, longitude: longitude, viewport: viewport, visiblePlaceIds: visiblePlaceIds,
            openNow: openNow, budgetMax: budgetMax, minRating: minRating
        )
        return try await post("places/nearby/pick", body: body)
    }

    private struct EmptyBody: Encodable {}

    private static func get<Response: Decodable>(_ path: String, query: [URLQueryItem]) async throws -> Response {
        var components = URLComponents(url: APIConfig.baseURL.appendingPathComponent(path), resolvingAgainstBaseURL: false)
        components?.queryItems = query
        guard let url = components?.url else { throw APIError.invalidResponse }

        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Accept")

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
        guard (200..<300).contains(httpResponse.statusCode) else {
            throw APIError.server(statusCode: httpResponse.statusCode)
        }

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }

    private static func post<Body: Encodable, Response: Decodable>(
        _ path: String, body: Body, clientToken: String? = nil
    ) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.timeoutInterval = 15
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let clientToken {
            request.setValue(clientToken, forHTTPHeaderField: "X-Decision-Token")
        }
        request.httpBody = try encoder.encode(body)

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
        guard (200..<300).contains(httpResponse.statusCode) else {
            throw APIError.server(statusCode: httpResponse.statusCode)
        }

        do {
            return try decoder.decode(Response.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }
}
