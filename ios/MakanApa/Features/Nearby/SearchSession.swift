import CoreLocation
import Foundation

/// One Nearby search, from the query that started it to whatever the user tapped — the single
/// object every search transition works on, instead of query/results/selection scattered across
/// the view model:
///
///     tap a result      → same session, `selectedResultId` changes
///     back to results   → same session
///     show all on map   → same session, `presentation = .map`
///     search wider      → same query, larger `radiusKm`, fresh results
///
/// It's only thrown away when the user clears the search or types a new query.
struct SearchSession: Equatable {
    enum Presentation: Equatable {
        /// Full-height results panel over the map.
        case list
        /// Result pins on the map plus a bottom carousel, in the same order as the list.
        case map
    }

    let query: String
    let centerLatitude: Double
    let centerLongitude: Double
    let radiusKm: Double
    var results: [PlaceSearchResult]
    var meta: PlaceSearchMeta?
    var suggestions: [String]
    var selectedResultId: String?
    var presentation: Presentation = .list
    let searchedAt: Date

    var center: CLLocationCoordinate2D {
        CLLocationCoordinate2D(latitude: centerLatitude, longitude: centerLongitude)
    }

    /// local | google | mixed — reported back with "Makan sini".
    var source: String { meta?.source ?? "local" }

    var selectedResult: PlaceSearchResult? {
        results.first { $0.id == selectedResultId }
    }

    /// The other likely branches of the same chain as `result`, nearest first (list order).
    func otherBranches(of result: PlaceSearchResult) -> [PlaceSearchResult] {
        guard let key = result.groupKey else { return [] }
        return results.filter { $0.groupKey == key && $0.id != result.id }
    }

    /// True for the first row of a branch group — where the "KFC · 3 nearby" cue goes.
    func startsGroup(at index: Int) -> Bool {
        guard results.indices.contains(index), let key = results[index].groupKey else { return false }
        return index == 0 || results[index - 1].groupKey != key
    }
}

/// Why the map camera last moved. Only a user's own drag or pinch may raise "Search this area" —
/// a camera move the app made (focusing a search result, fitting all results) must not.
enum MapMoveOrigin: Equatable {
    case user
    case searchSelection
    case showAllResults
    case recommendation
    case programmatic
}

/// The last few queries that actually led somewhere — on this device only, never sent to the
/// backend.
enum RecentSearchStore {
    private static let key = "RecentSearchStore.queries"
    private static let limit = 5

    static var queries: [String] {
        UserDefaults.standard.stringArray(forKey: key) ?? []
    }

    static func record(_ query: String) {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        guard trimmed.count >= 2 else { return }
        var updated = queries.filter { $0.caseInsensitiveCompare(trimmed) != .orderedSame }
        updated.insert(trimmed, at: 0)
        UserDefaults.standard.set(Array(updated.prefix(limit)), forKey: key)
    }

    static func remove(_ query: String) {
        UserDefaults.standard.set(queries.filter { $0 != query }, forKey: key)
    }
}
