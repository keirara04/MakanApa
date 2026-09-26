import Foundation

/// Every filter that shapes a `places/nearby` response — halal and mode change which pins the
/// map gets, the chips change the area summary — so a cached response is only reused when all
/// of them still match.
struct NearbyFilterSignature: Codable, Equatable {
    let openNow: Bool
    let budgetMax: Int?
    let minRating: Double?
    let mode: DiscoveryMode
    let vibe: Vibe?
    let halal: Bool
}

/// The last successful `places/nearby` response, plus what it was fetched for.
struct NearbyPlacesSnapshot: Codable, Equatable {
    let viewport: MapViewport
    let filters: NearbyFilterSignature
    let places: [NearbyPlace]
    let areaSummary: AreaSummaryResponse
    let savedAt: Date

    /// Old enough and open status / "open now" counts are more wrong than useful to paint first.
    static let maxAge: TimeInterval = 12 * 60 * 60

    /// Worth painting before the fresh fetch lands: recent, fetched with the same filters, and
    /// the map is looking at roughly the same spot (its center falls inside the cached area).
    func isUsable(for viewport: MapViewport, filters: NearbyFilterSignature, now: Date = Date()) -> Bool {
        guard filters == self.filters, now.timeIntervalSince(savedAt) < Self.maxAge else { return false }
        let centerLatitude = (viewport.north + viewport.south) / 2
        let centerLongitude = (viewport.east + viewport.west) / 2
        return (self.viewport.south...self.viewport.north).contains(centerLatitude)
            && (self.viewport.west...self.viewport.east).contains(centerLongitude)
    }
}

/// One-slot disk cache so reopening Nearby in the same area shows its rating pills straight
/// away while the fresh fetch runs (stale-while-revalidate) — a cold area can mean a Google
/// Places round trip server-side. Caches directory: the OS may purge it, which just means one
/// slower first paint.
enum NearbyPlacesCache {
    static let defaultURL = FileManager.default
        .urls(for: .cachesDirectory, in: .userDomainMask)[0]
        .appendingPathComponent("nearby-places.json")

    static func load(from url: URL = defaultURL) -> NearbyPlacesSnapshot? {
        guard let data = try? Data(contentsOf: url) else { return nil }
        return try? JSONDecoder().decode(NearbyPlacesSnapshot.self, from: data)
    }

    /// Encodes and writes off the main thread — the fetch that produced it already landed.
    static func save(_ snapshot: NearbyPlacesSnapshot, to url: URL = defaultURL) {
        Task.detached(priority: .utility) {
            guard let data = try? JSONEncoder().encode(snapshot) else { return }
            try? data.write(to: url, options: .atomic)
        }
    }

    /// On sign-out / account deletion — the next person on this device shouldn't open to the
    /// last one's area.
    static func clear(at url: URL = defaultURL) {
        try? FileManager.default.removeItem(at: url)
    }
}
