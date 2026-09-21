import CryptoKit
import Foundation
import Security

/// Anti-replay defense for Sign in with Apple: a fresh raw nonce is generated per attempt, its
/// SHA256 hash is what's sent to Apple in the authorization request, and the raw value is kept
/// only in memory to send to the backend alongside the returned identity token — the backend
/// recomputes the hash and compares it against the token's embedded `nonce` claim.
enum AppleNonce {
    static func randomRawNonce(length: Int = 32) -> String {
        let charset: [Character] = Array("0123456789ABCDEFGHIJKLMNOPQRSTUVXYZabcdefghijklmnopqrstuvwxyz-._")
        var randomBytes = [UInt8](repeating: 0, count: length)
        let status = SecRandomCopyBytes(kSecRandomDefault, randomBytes.count, &randomBytes)
        precondition(status == errSecSuccess, "Unable to generate secure random nonce")

        return String(randomBytes.map { byte in
            charset[Int(byte) % charset.count]
        })
    }

    static func sha256Hex(_ input: String) -> String {
        SHA256.hash(data: Data(input.utf8))
            .compactMap { String(format: "%02x", $0) }
            .joined()
    }
}
