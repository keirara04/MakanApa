import CoreLocation
import MapKit
import UIKit

struct MapDestination {
    let coordinate: CLLocationCoordinate2D
    let name: String
    let googleMapsURL: URL?
}

/// Stateless by design — the provider is passed in rather than read from
/// `MapProviderPreference` here, so each branch is directly testable.
enum PreferredMapsLauncher {
    static func open(destination: MapDestination, provider: MapProvider) {
        switch provider {
        case .apple:
            let placemark = MKPlacemark(coordinate: destination.coordinate)
            let mapItem = MKMapItem(placemark: placemark)
            mapItem.name = destination.name
            mapItem.openInMaps()
        case .google:
            UIApplication.shared.open(googleURL(for: destination))
        case .naver:
            open(appURL: naverAppURL(destination), webURL: naverWebURL(destination))
        case .kakao:
            if let url = kakaoRouteURL(destination) { UIApplication.shared.open(url) }
        }
    }

    private static func open(appURL: URL?, webURL: URL?) {
        if let appURL, UIApplication.shared.canOpenURL(appURL) {
            UIApplication.shared.open(appURL)
        } else if let webURL {
            UIApplication.shared.open(webURL)
        }
    }

    /// Prefers the backend-provided Google Maps URL (exact place); falls back to a
    /// coordinate-based universal Maps URL (opens the app if installed, else browser —
    /// Google's documented cross-platform pattern, no `canOpenURL` check needed).
    static func googleURL(for destination: MapDestination) -> URL {
        if let googleMapsURL = destination.googleMapsURL {
            return googleMapsURL
        }
        var components = URLComponents(string: "https://www.google.com/maps/search/")!
        components.queryItems = [
            URLQueryItem(name: "api", value: "1"),
            URLQueryItem(name: "query", value: "\(destination.coordinate.latitude),\(destination.coordinate.longitude)"),
        ]
        return components.url!
    }

    static func naverAppURL(_ destination: MapDestination) -> URL? {
        var components = URLComponents()
        components.scheme = "nmap"
        components.host = "place"
        components.queryItems = [
            URLQueryItem(name: "lat", value: String(destination.coordinate.latitude)),
            URLQueryItem(name: "lng", value: String(destination.coordinate.longitude)),
            URLQueryItem(name: "name", value: destination.name),
            URLQueryItem(name: "appname", value: Bundle.main.bundleIdentifier),
        ]
        return components.url
    }

    /// Kakao's documented web link ("map.kakao.com/link/to/name,lat,lng") — directions to the
    /// place; no app scheme, so no LSApplicationQueriesSchemes entry needed.
    static func kakaoRouteURL(_ destination: MapDestination) -> URL? {
        let name = destination.name.addingPercentEncoding(withAllowedCharacters: .urlPathAllowed.subtracting(CharacterSet(charactersIn: ","))) ?? ""
        return URL(string: "https://map.kakao.com/link/to/\(name),\(destination.coordinate.latitude),\(destination.coordinate.longitude)")
    }

    static func naverWebURL(_ destination: MapDestination) -> URL? {
        var components = URLComponents(string: "https://m.map.naver.com/search2/search.naver")
        components?.queryItems = [URLQueryItem(name: "query", value: destination.name)]
        return components?.url
    }
}
