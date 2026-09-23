import Foundation

/// Context signals ("☔ Hujan", "🌙 Supper", …) the user switched off in the "Right now" strip.
/// Sent with every decision as `ignoreContext`, so a switched-off signal never nudges picks or
/// shows up as a reason. Local-only by design — it's a per-device "don't care about rain" choice.
enum ContextPreferences {
    private static let key = "brain.ignoredContext"

    static var ignoredKeys: [String] {
        get { UserDefaults.standard.stringArray(forKey: key) ?? [] }
        set { UserDefaults.standard.set(Array(Set(newValue)).sorted(), forKey: key) }
    }

    static func isIgnored(_ key: String) -> Bool {
        ignoredKeys.contains(key)
    }

    static func toggle(_ key: String) {
        if isIgnored(key) {
            ignoredKeys.removeAll { $0 == key }
        } else {
            ignoredKeys.append(key)
        }
    }
}
