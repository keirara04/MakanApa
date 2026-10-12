#if DEBUG
import CoreLocation
import Observation

/// DEBUG-only escape hatch: lets development use a test area instead of the simulator/device's
/// real GPS. Bangi is where the fixture restaurants are clustered; Korea University (Seoul) is
/// for testing the Kakao Map search and picks; Penn State (State College) is for the US region
/// (miles, $, US moods). At most one is on. Never compiled into Release.
@MainActor
enum DebugLocationOverride {
    static let fixtureTestArea = CLLocationCoordinate2D(latitude: 2.928400, longitude: 101.780200)
    static let koreaTestArea = CLLocationCoordinate2D(latitude: 37.589400, longitude: 127.027600)
    static let usaTestArea = CLLocationCoordinate2D(latitude: 40.798200, longitude: -77.859900)

    private static let fixtureKey = "debug.useFixtureLocation"
    private static let koreaKey = "debug.useKoreaLocation"
    private static let usaKey = "debug.useUSALocation"

    static var isEnabled: Bool {
        get { UserDefaults.standard.bool(forKey: fixtureKey) }
        set { turn(fixtureKey, on: newValue) }
    }

    static var isKoreaEnabled: Bool {
        get { UserDefaults.standard.bool(forKey: koreaKey) }
        set { turn(koreaKey, on: newValue) }
    }

    static var isUSAEnabled: Bool {
        get { UserDefaults.standard.bool(forKey: usaKey) }
        set { turn(usaKey, on: newValue) }
    }

    static var activeCoordinate: CLLocationCoordinate2D? {
        if isKoreaEnabled { return koreaTestArea }
        if isUSAEnabled { return usaTestArea }
        return isEnabled ? fixtureTestArea : nil
    }

    /// Turning one on turns the others off.
    private static func turn(_ key: String, on: Bool) {
        if on {
            for other in [fixtureKey, koreaKey, usaKey] where other != key {
                UserDefaults.standard.set(false, forKey: other)
            }
        }
        UserDefaults.standard.set(on, forKey: key)
    }
}
#endif
