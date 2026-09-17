import Foundation

/// Anonymous, client-generated device id, kept alongside the authenticated user's id (see
/// `AuthStore`) rather than replaced by it. Used for save/unsave idempotency (paired with a
/// unique restaurant_id+installation_id row server-side) and for "For you" personalization
/// (the server looks up this installation's own accept history).
enum InstallationID {
    private static let key = "InstallationID.value"

    static let current: String = {
        if let existing = UserDefaults.standard.string(forKey: key) {
            return existing
        }
        let generated = UUID().uuidString
        UserDefaults.standard.set(generated, forKey: key)
        return generated
    }()
}
