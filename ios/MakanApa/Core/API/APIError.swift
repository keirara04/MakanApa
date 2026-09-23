import Foundation

enum APIError: Error {
    case invalidResponse
    case unauthorized
    case server(statusCode: Int)
    /// A 4xx whose body carried a user-facing message (Laravel's `message`/first validation
    /// error) — only thrown by endpoints that opt into it via `APIClient.sendSurfacingMessage`.
    case rejected(statusCode: Int, message: String)
    case decoding(Error)
    case transport(Error)
    /// HTTP 429 — the server's per-feature rate limit. `retryAfterSeconds` comes from the
    /// `Retry-After` header when the server sent one.
    case rateLimited(retryAfterSeconds: Int?)
}

extension APIError {
    /// The server's own wording when there is one, else nil — callers fall back to generic copy.
    var serverMessage: String? {
        if case .rejected(_, let message) = self { return message }
        return nil
    }
}

extension APIError {
    /// Shared headline/detail for full-screen and banner error states, so every surface explains
    /// a rate limit or a dropped connection the same way instead of a generic "blur kejap".
    var userFacingCopy: (headline: String, detail: String) {
        switch self {
        case .transport:
            return (Copy.connectionErrorHeadline, Copy.connectionErrorDetail)
        case .rateLimited(let retryAfterSeconds):
            return (Copy.rateLimitedHeadline, Copy.rateLimitedDetail(retryAfterSeconds: retryAfterSeconds))
        default:
            return (Copy.genericAPIErrorHeadline, Copy.genericAPIErrorDetail)
        }
    }
}
