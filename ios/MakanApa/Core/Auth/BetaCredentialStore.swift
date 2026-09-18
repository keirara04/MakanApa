import Foundation
import Security

struct BetaCredential: Codable {
    let email: String
    let password: String
}

/// Persists admin-generated beta account temp passwords on-device (Keychain) so an admin can
/// reopen a beta user later and still see/copy the credentials, not just at creation time.
/// Separate service string from CredentialStore's session token so the two items never collide.
@MainActor
final class BetaCredentialStore {
    static let shared = BetaCredentialStore()

    private let service = "com.makanapa.betacreds"
    private let account = "credentials"

    private init() {}

    func credential(for id: Int) -> BetaCredential? {
        all()[id]
    }

    func save(id: Int, email: String, password: String) {
        var current = all()
        current[id] = BetaCredential(email: email, password: password)
        write(current)
    }

    func clear(id: Int) {
        var current = all()
        current.removeValue(forKey: id)
        write(current)
    }

    private func all() -> [Int: BetaCredential] {
        guard let data = read(),
              let decoded = try? JSONDecoder().decode([Int: BetaCredential].self, from: data) else {
            return [:]
        }
        return decoded
    }

    private func read() -> Data? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne,
        ]
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        guard status == errSecSuccess, let data = result as? Data else { return nil }
        return data
    }

    private func write(_ value: [Int: BetaCredential]) {
        guard let data = try? JSONEncoder().encode(value) else { return }
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
        ]
        let attributes: [String: Any] = [
            kSecValueData as String: data,
        ]
        if SecItemCopyMatching(query as CFDictionary, nil) == errSecSuccess {
            SecItemUpdate(query as CFDictionary, attributes as CFDictionary)
        } else {
            var newItem = query
            newItem[kSecValueData as String] = data
            SecItemAdd(newItem as CFDictionary, nil)
        }
    }
}
