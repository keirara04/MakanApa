#if DEBUG
import CoreLocation
import Observation

/// DEBUG-only escape hatch: lets development use a test area instead of the simulator/device's
/// real GPS. Bangi is where the fixture restaurants are clustered; Korea University (Seoul) is
/// for testing the Kakao Map search and picks. At most one is on. Never compiled into Release.
@MainActor
enum DebugLocationOverride {
    static let fixtureTestArea = CLLocationCoordinate2D(latitude: 2.928400, longitude: 101.780200)
    static let koreaTestArea = CLLocationCoordinate2D(latitude: 37.589400, longitude: 127.027600)

    static var isEnabled: Bool {
        get { UserDefaults.standard.bool(forKey: "debug.useFixtureLocation") }
        set {
            UserDefaults.standard.set(newValue, forKey: "debug.useFixtureLocation")
            if newValue { UserDefaults.standard.set(false, forKey: "debug.useKoreaLocation") }
        }
    }

    static var isKoreaEnabled: Bool {
        get { UserDefaults.standard.bool(forKey: "debug.useKoreaLocation") }
        set {
            UserDefaults.standard.set(newValue, forKey: "debug.useKoreaLocation")
            if newValue { UserDefaults.standard.set(false, forKey: "debug.useFixtureLocation") }
        }
    }

    static var activeCoordinate: CLLocationCoordinate2D? {
        if isKoreaEnabled { return koreaTestArea }
        return isEnabled ? fixtureTestArea : nil
    }
}
#endif
