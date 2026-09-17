import Foundation
import Security

/// Owns only Bearer token persistence, backed by the Keychain (not UserDefaults — this is a
/// real session credential, unlike the local dev-only toggles elsewhere in Core/Data). Kept as
/// a separate, tiny type — rather than folding token storage into AuthStore — specifically so
/// APIClient can read the token without depending on AuthStore, which itself depends on
/// APIClient to make requests. Two mutual imports would deadlock the dependency graph.
@MainActor
final class CredentialStore {
    static let shared = CredentialStore()

    private let service = "com.makanapa.auth"
    private let account = "sessionToken"

    var token: String? {
        get { read() }
        set {
            if let newValue {
                write(newValue)
            } else {
                delete()
            }
        }
    }

    private init() {}

    private func read() -> String? {
        var query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne,
        ]
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        query.removeValue(forKey: kSecReturnData as String)
        guard status == errSecSuccess, let data = result as? Data else { return nil }
        return String(data: data, encoding: .utf8)
    }

    private func write(_ value: String) {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
        ]
        let attributes: [String: Any] = [
            kSecValueData as String: Data(value.utf8),
        ]
        if SecItemCopyMatching(query as CFDictionary, nil) == errSecSuccess {
            SecItemUpdate(query as CFDictionary, attributes as CFDictionary)
        } else {
            var newItem = query
            newItem[kSecValueData as String] = Data(value.utf8)
            SecItemAdd(newItem as CFDictionary, nil)
        }
    }

    private func delete() {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
        ]
        SecItemDelete(query as CFDictionary)
    }
}
