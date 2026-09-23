import UIKit
import UserNotifications

/// Bridges UIKit's push notification callbacks into the SwiftUI app via `UIApplicationDelegateAdaptor`.
/// Stays dumb on purpose: registers the device token and reports which `DeepLinkDestination` was
/// tapped — it never decides what either of those things should *do* in the app.
final class PushNotificationDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        registerIfAlreadyAllowed()
        return true
    }

    /// The priming sheet only asks once, so a registration that failed then (or a token Apple
    /// rotated since) would never reach the backend again. Re-registering on every launch once
    /// permission exists is Apple's recommended pattern and costs nothing when nothing changed.
    private func registerIfAlreadyAllowed() {
        Task { @MainActor in
            let settings = await UNUserNotificationCenter.current().notificationSettings()
            switch settings.authorizationStatus {
            case .authorized, .provisional, .ephemeral:
                UIApplication.shared.registerForRemoteNotifications()
            default:
                break
            }
        }
    }

    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        let hexToken = deviceToken.map { String(format: "%02.2hhx", $0) }.joined()
        DeviceTokenStore.current = hexToken

        Task {
            try? await APIClient.registerDeviceToken(
                installationId: InstallationID.current, token: hexToken, environment: PushEnvironment.current
            )

            // A login that already happened before this callback fired (e.g. token rotated
            // mid-session) needs the fresh token claimed too, not just registered anonymously.
            let session = await MainActor.run { AuthStore.shared.session }
            if case .authenticated = session {
                try? await APIClient.claimDeviceToken(
                    installationId: InstallationID.current, token: hexToken, environment: PushEnvironment.current
                )
            }
        }
    }

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        #if DEBUG
        print("[PushNotificationDelegate] didFailToRegisterForRemoteNotifications: \(error)")
        #endif
    }

    /// Foreground presentation — banner + sound even while the app is already open.
    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter, willPresent notification: UNNotification
    ) async -> UNNotificationPresentationOptions {
        [.banner, .sound]
    }

    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse
    ) async {
        guard let destination = DeepLinkDestination(userInfo: response.notification.request.content.userInfo) else { return }
        await MainActor.run {
            PendingDeepLink.shared.destination = destination
        }
    }
}
