import Foundation

enum APIError: Error {
    case invalidResponse
    case unauthorized
    case server(statusCode: Int)
    case decoding(Error)
    case transport(Error)
}
