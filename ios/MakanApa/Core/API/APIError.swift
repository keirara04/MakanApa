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
}

extension APIError {
    /// The server's own wording when there is one, else nil — callers fall back to generic copy.
    var serverMessage: String? {
        if case .rejected(_, let message) = self { return message }
        return nil
    }
}
