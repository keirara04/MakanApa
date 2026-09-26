import CoreLocation
import Observation

enum LocationState: Equatable {
    case notDetermined
    case authorized(CLLocationCoordinate2D)
    case denied
    case unavailable

    static func == (lhs: LocationState, rhs: LocationState) -> Bool {
        switch (lhs, rhs) {
        case (.notDetermined, .notDetermined), (.denied, .denied), (.unavailable, .unavailable):
            return true
        case let (.authorized(a), .authorized(b)):
            return a.latitude == b.latitude && a.longitude == b.longitude
        default:
            return false
        }
    }
}

@MainActor
@Observable
final class LocationService: NSObject, CLLocationManagerDelegate {
    private(set) var state: LocationState = .notDetermined
    /// The raw permission, tracked apart from `state` — `state` stays `.notDetermined` while an
    /// already-allowed app waits for its first fix, which would otherwise read as "never asked."
    private(set) var authorization: CLAuthorizationStatus = .notDetermined
    /// False when the user only granted Approximate Location.
    private(set) var isPrecise = true

    private let manager = CLLocationManager()

    override init() {
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = kCLLocationAccuracyHundredMeters
        refreshAuthorizationState()
    }

    func requestLocation() {
        if let override = debugOverrideCoordinate() {
            state = .authorized(override)
            return
        }

        switch manager.authorizationStatus {
        case .notDetermined:
            manager.requestWhenInUseAuthorization()
        case .authorizedWhenInUse, .authorizedAlways:
            manager.requestLocation()
        case .denied, .restricted:
            state = .denied
        @unknown default:
            state = .unavailable
        }
    }

    private func refreshAuthorizationState() {
        authorization = manager.authorizationStatus
        isPrecise = manager.accuracyAuthorization == .fullAccuracy
        switch manager.authorizationStatus {
        case .notDetermined:
            state = .notDetermined
        case .denied, .restricted:
            state = .denied
        case .authorizedWhenInUse, .authorizedAlways:
            // Checked here too, not just in requestLocation() — otherwise an authorization-change
            // callback (e.g. app foregrounding) calls manager.requestLocation() directly and
            // silently overwrites the test coordinate with real GPS once didUpdateLocations fires.
            if let override = debugOverrideCoordinate() {
                state = .authorized(override)
            } else {
                manager.requestLocation()
            }
        @unknown default:
            state = .unavailable
        }
    }

    private func debugOverrideCoordinate() -> CLLocationCoordinate2D? {
        #if DEBUG
        return DebugLocationOverride.activeCoordinate
        #else
        return nil
        #endif
    }

    nonisolated func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        Task { @MainActor in
            self.refreshAuthorizationState()
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        guard let coordinate = locations.last?.coordinate else { return }
        Task { @MainActor in
            self.state = .authorized(coordinate)
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        Task { @MainActor in
            self.state = .unavailable
        }
    }
}
