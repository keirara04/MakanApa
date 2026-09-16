#if DEBUG
import CoreLocation
import Observation

/// DEBUG-only escape hatch: lets development use the Bangi fixture test area
/// instead of the simulator/device's real GPS, since fixture restaurants are
/// clustered there. Never compiled into Release builds.
@MainActor
enum DebugLocationOverride {
    static let fixtureTestArea = CLLocationCoordinate2D(latitude: 2.928400, longitude: 101.780200)

    static var isEnabled: Bool {
        get { UserDefaults.standard.bool(forKey: "debug.useFixtureLocation") }
        set { UserDefaults.standard.set(newValue, forKey: "debug.useFixtureLocation") }
    }

    static var activeCoordinate: CLLocationCoordinate2D? {
        isEnabled ? fixtureTestArea : nil
    }
}
#endif
