import Foundation

enum MapProvider: String, CaseIterable, Identifiable {
    case apple
    case google
    case naver

    var id: String { rawValue }

    var label: String {
        switch self {
        case .apple: "Apple Maps"
        case .google: "Google Maps"
        case .naver: "Naver Maps"
        }
    }
}

enum MapProviderPreference {
    private static let key = "settings.preferredMapProvider"

    static var current: MapProvider {
        get { UserDefaults.standard.string(forKey: key).flatMap(MapProvider.init) ?? .apple }
        set { UserDefaults.standard.set(newValue.rawValue, forKey: key) }
    }
}
