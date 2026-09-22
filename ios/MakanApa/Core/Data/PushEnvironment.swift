import Foundation

/// Resolves sandbox vs production purely from the build configuration — matches which
/// `aps-environment` entitlement Xcode actually signs the build with (development for
/// Debug/TestFlight-via-Xcode, production for Release/App Store), so the value sent to
/// `POST device-tokens` always matches the APNs gateway the token was actually issued for.
enum PushEnvironment {
    static var current: String {
        #if DEBUG
        return "sandbox"
        #else
        return "production"
        #endif
    }
}
