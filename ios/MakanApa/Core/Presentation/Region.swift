import CoreLocation
import Foundation

/// Where the user is, for what reads differently by country: prices, miles, the Decide moods,
/// onboarding samples. Mirrors api/app/Support/Region.php and web/src/lib/region.ts; keep the
/// boxes and labels in step. Rough boxes (they take in some sea and border land, harmless).
enum Region: String {
    case malaysia = "MY"
    case korea = "KR"
    case unitedStates = "US"
    case other = "OTHER"

    /// Peninsular Malaysia stops short of southern Thailand and Batam; Singapore, Toronto,
    /// Ottawa–Montreal–Quebec and everything north of 49° (Vancouver) are cut out of their boxes.
    static func at(_ coordinate: CLLocationCoordinate2D) -> Region {
        let (lat, lng) = (coordinate.latitude, coordinate.longitude)
        func within(_ south: Double, _ north: Double, _ west: Double, _ east: Double) -> Bool {
            (south...north).contains(lat) && (west...east).contains(lng)
        }
        switch true {
        case within(1.15, 1.47, 103.6, 104.1): return .other // Singapore
        case within(1.2, 6.8, 99.5, 104.6), within(0.8, 7.5, 109.5, 119.5): return .malaysia
        case within(33.0, 38.7, 124.5, 131.0): return .korea
        case within(43.4, 44.6, -80.6, -78.4), within(45.0, 46.9, -76.5, -71.0): return .other // Toronto, Ottawa–Montreal–Quebec
        case within(24.4, 49.0, -125.0, -66.9): return .unitedStates
        default: return .other
        }
    }

    /// A place's region from its coordinates, else the user's.
    static func of(latitude: Double?, longitude: Double?) -> Region {
        guard let latitude, let longitude else { return current }
        return at(CLLocationCoordinate2D(latitude: latitude, longitude: longitude))
    }

    private static let storageKey = "Region.lastKnown"

    /// The region of the latest location fix, else a guess from the device time zone (onboarding
    /// runs before location). Plain UserDefaults, read on demand, so it never drives a re-render.
    static var current: Region {
        UserDefaults.standard.string(forKey: storageKey).flatMap(Region.init(rawValue:))
            ?? fromTimeZone(TimeZone.current.identifier)
    }

    /// Writes only when the region changes: every fix landing in UserDefaults would wake any
    /// UserDefaults observer near the map (see the no-@AppStorage-around-map rule).
    static func remember(_ coordinate: CLLocationCoordinate2D) {
        let region = at(coordinate).rawValue
        if UserDefaults.standard.string(forKey: storageKey) != region {
            UserDefaults.standard.set(region, forKey: storageKey)
        }
    }

    static func fromTimeZone(_ identifier: String) -> Region {
        let usZones: Set = [
            "America/New_York", "America/Chicago", "America/Denver", "America/Phoenix",
            "America/Los_Angeles", "America/Anchorage", "America/Detroit", "America/Boise", "Pacific/Honolulu",
        ]
        switch identifier {
        case "Asia/Seoul": return .korea
        case "Asia/Kuala_Lumpur", "Asia/Kuching": return .malaysia
        case _ where usZones.contains(identifier)
            || ["America/Indiana/", "America/Kentucky/", "America/North_Dakota/"].contains(where: identifier.hasPrefix):
            return .unitedStates
        default: return .other
        }
    }

    var isMalaysia: Bool { self == .malaysia }

    // MARK: - Prices

    /// Solo wizard budget tiles, price levels 1–3.
    var budgetAmounts: [String] {
        switch self {
        case .malaysia: return ["~RM10", "~RM20", "~RM35+"]
        case .korea: return ["~₩10,000", "~₩18,000", "~₩30,000+"]
        case .unitedStates: return ["~$12", "~$25", "~$40+"]
        case .other: return ["$", "$$", "$$$"]
        }
    }

    /// "≈ RM20/person" from Google's coarse price level.
    func approximateSpend(_ priceLevel: Int?) -> String? {
        guard let priceLevel, (1...3).contains(priceLevel) else { return nil }
        switch self {
        case .other: return ["Budget", "Mid-range", "Pricey"][priceLevel - 1]
        default: return "≈ \(budgetAmounts[priceLevel - 1].dropFirst())/person"
        }
    }

    /// The Nearby "price level 2 or less" filter.
    var budgetFilterLabel: String {
        switch self {
        case .malaysia: return "≤ RM20"
        case .korea: return "≤ ₩18,000"
        case .unitedStates: return "≤ $25"
        case .other: return "$$ or less"
        }
    }

    /// Nearby area summary count, e.g. "12 under RM20".
    var budgetFriendlyLabel: String {
        switch self {
        case .malaysia: return "under RM20"
        case .korea: return "under ₩18,000"
        case .unitedStates: return "under $25"
        case .other: return "budget-friendly"
        }
    }

    /// Menu prices; empty where there's no single local currency.
    var currencySymbol: String {
        switch self {
        case .malaysia: return "RM"
        case .korea: return "₩"
        case .unitedStates: return "$"
        case .other: return ""
        }
    }

    /// Add-place "spend per person" → price level: below the first number is 1, below the second 2, else 3.
    var spendLevelCeilings: (Double, Double) {
        self == .korea ? (12_000, 25_000) : (15, 30)
    }

    /// Spend prefilled from a price level 1–3 (Google's tier), and the field's placeholder.
    var spendExamples: (levels: [String], placeholder: String) {
        switch self {
        case .malaysia: return (["10", "20", "35"], "15")
        case .korea: return (["10000", "18000", "30000"], "12000")
        default: return (["12", "25", "40"], "15")
        }
    }

    func price(_ amount: Double) -> String {
        self == .korea
            ? currencySymbol + amount.formatted(.number.precision(.fractionLength(0)).locale(Locale(identifier: "en_US")))
            : currencySymbol + String(format: "%.2f", amount)
    }

    // MARK: - Distance

    var usesMiles: Bool { self == .unitedStates }

    /// "1.2 km" (or "0.7 mi" in the US); `kmText` is the caller's own km wording, kept as is elsewhere.
    func distance(km: Double, kmText: String) -> String {
        usesMiles ? "\((km * 0.621371).formatted(.number.precision(.fractionLength(1)))) mi" : kmText
    }

    /// A search radius: "Within 2 km" / "Within 1.2 mi".
    func radius(_ km: Double) -> String {
        usesMiles ? "\((km * 0.621371).formatted(.number.precision(.fractionLength(0...1)))) mi" : "\(km.formatted()) km"
    }

    // MARK: - Content

    var searchExamples: [String] {
        switch self {
        case .malaysia: return ["nasi lemak", "mamak", "coffee", "chicken rice"]
        case .korea: return ["kebab", "halal", "indian", "coffee"]
        case .unitedStates: return ["halal cart", "indian", "coffee", "kebab"]
        case .other: return ["kebab", "halal", "indian", "coffee"]
        }
    }

    /// Form placeholders and suggestions that would otherwise only make sense in Malaysia.
    var formExamples: (placeName: String, category: String, categories: [String], phone: String, university: String, area: String) {
        switch self {
        case .malaysia: return (
            "e.g. Restoran Nasi Kandar Pelita", "e.g. Mamak",
            ["Mamak", "Nasi campur", "Kopitiam", "Cafe", "Western", "Chinese", "Indian", "Warung", "Dessert", "Street food"],
            "012-345 6789", "e.g. Universiti Malaya", "e.g. Shah Alam, Cheras, your neighbourhood"
        )
        case .korea: return (
            "e.g. Anam Kebab House", "e.g. Middle Eastern",
            ["Halal", "Middle Eastern", "Indian", "Korean", "Fried chicken", "Cafe", "Dessert", "Street food"],
            "010-1234-5678", "e.g. Korea University", "e.g. Anam, Itaewon, your neighbourhood"
        )
        case .unitedStates: return (
            "e.g. Sultan Halal Grill", "e.g. Middle Eastern",
            ["Halal", "Middle Eastern", "Indian", "Fried chicken", "Burgers", "Pizza", "Cafe", "Dessert"],
            "(814) 555-0123", "e.g. Penn State", "e.g. State College, your neighbourhood"
        )
        case .other: return (
            "e.g. Istanbul Kebab House", "e.g. Middle Eastern",
            ["Halal", "Middle Eastern", "Indian", "Fried chicken", "Burgers", "Pizza", "Cafe", "Dessert"],
            "Phone number", "e.g. your university", "e.g. your neighbourhood"
        )
        }
    }

    /// Where the pin picker opens without a location.
    var pinDefault: CLLocationCoordinate2D {
        switch self {
        case .korea: return CLLocationCoordinate2D(latitude: 37.5894, longitude: 127.0276)
        case .unitedStates: return CLLocationCoordinate2D(latitude: 40.7982, longitude: -77.8599)
        default: return CLLocationCoordinate2D(latitude: 3.1390, longitude: 101.6869)
        }
    }
}
