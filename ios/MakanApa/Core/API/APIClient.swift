import Foundation

enum APIConfig {
    #if DEBUG
    // Real device can't reach the Mac via 127.0.0.1 (that's the phone itself) — use the
    // Mac's LAN IP instead. Find it with `ipconfig getifaddr en0`; update if it changes
    // (different network, DHCP renewal). Simulator works with either since it shares the
    // host's network namespace.
    static let baseURL = URL(string: "http://10.121.215.167:8000/api/v1")!
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

    static func reroll(decisionId: Int) async throws -> RerollResponse {
        try await post("decisions/\(decisionId)/reroll", body: EmptyBody())
    }

    static func accept(decisionId: Int) async throws -> AcceptResponse {
        try await post("decisions/\(decisionId)/accept", body: EmptyBody())
    }

    private struct EmptyBody: Encodable {}

    private static func post<Body: Encodable, Response: Decodable>(
        _ path: String, body: Body
    ) async throws -> Response {
        var request = URLRequest(url: APIConfig.baseURL.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
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
