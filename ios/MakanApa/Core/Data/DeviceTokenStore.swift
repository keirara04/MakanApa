import Foundation

/// Caches the last hex-encoded APNs device token locally — `PushNotificationDelegate` only ever
/// receives it transiently (via a UIKit callback), but `AuthStore` needs it again later for
/// claim() on login and unclaim() on logout.
enum DeviceTokenStore {
    private static let key = "DeviceTokenStore.token"

    static var current: String? {
        get { UserDefaults.standard.string(forKey: key) }
        set { UserDefaults.standard.set(newValue, forKey: key) }
    }
}
