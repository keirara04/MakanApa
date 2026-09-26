import XCTest
@testable import MakanApa

/// Pins when Nearby may paint its last cached response before the fresh fetch lands — same
/// filters, recent enough, and the map looking at the cached area — and that a snapshot
/// survives the trip to disk intact.
final class NearbyPlacesCacheTests: XCTestCase {
    private let cachedArea = MapViewport(north: 2.95, south: 2.90, east: 101.80, west: 101.75)
    private let filters = NearbyFilterSignature(
        openNow: false, budgetMax: nil, minRating: nil, mode: .normal, vibe: nil, halal: true
    )
    private let savedAt = Date(timeIntervalSince1970: 1_800_000_000)

    private func snapshot() -> NearbyPlacesSnapshot {
        let place = NearbyPlace(
            id: 7, name: "Warung Pak Mat", rating: 4.4, priceLevel: 1,
            latitude: 2.92, longitude: 101.77, openStatus: "open",
            halal: HalalSummary(
                status: .certified,
                display: HalalDisplay(shortLabel: "Halal", longLabel: "JAKIM certified", tone: "certified", action: nil, verificationLabel: nil)
            )
        )
        let summary = AreaSummaryResponse(
            placeCount: 1, openNowCount: 1, budgetFriendlyCount: 1,
            topCategories: [AreaCategoryCount(label: "Malay", count: 1)],
            topRated: [place], communityFinds: [],
            personalityTags: [AreaPersonalityTag(key: "budget_friendly", label: "Budget friendly")]
        )
        return NearbyPlacesSnapshot(viewport: cachedArea, filters: filters, places: [place], areaSummary: summary, savedAt: savedAt)
    }

    func testUsableForSameAreaAndFilters() {
        let slightlyPanned = MapViewport(north: 2.96, south: 2.91, east: 101.81, west: 101.76)
        XCTAssertTrue(snapshot().isUsable(for: slightlyPanned, filters: filters, now: savedAt.addingTimeInterval(60)))
    }

    func testNotUsableOnceMapCenterLeavesCachedArea() {
        let elsewhere = MapViewport(north: 3.20, south: 3.15, east: 101.70, west: 101.65)
        XCTAssertFalse(snapshot().isUsable(for: elsewhere, filters: filters, now: savedAt))
    }

    func testNotUsableWhenAnyFilterChanged() {
        // Halal decides which pins the map gets at all — a stale non-halal pin must never show.
        let halalOff = NearbyFilterSignature(openNow: false, budgetMax: nil, minRating: nil, mode: .normal, vibe: nil, halal: false)
        let openNow = NearbyFilterSignature(openNow: true, budgetMax: nil, minRating: nil, mode: .normal, vibe: nil, halal: true)

        XCTAssertFalse(snapshot().isUsable(for: cachedArea, filters: halalOff, now: savedAt))
        XCTAssertFalse(snapshot().isUsable(for: cachedArea, filters: openNow, now: savedAt))
    }

    func testNotUsablePastMaxAge() {
        let expired = savedAt.addingTimeInterval(NearbyPlacesSnapshot.maxAge + 1)
        XCTAssertFalse(snapshot().isUsable(for: cachedArea, filters: filters, now: expired))
    }

    func testSnapshotRoundTripsThroughDisk() throws {
        let url = FileManager.default.temporaryDirectory.appendingPathComponent("nearby-cache-\(UUID().uuidString).json")
        defer { NearbyPlacesCache.clear(at: url) }

        // `save` writes off the main thread; encode + write synchronously here instead so the
        // test doesn't race it, then read back through the real `load`.
        try JSONEncoder().encode(snapshot()).write(to: url)
        XCTAssertEqual(NearbyPlacesCache.load(from: url), snapshot())

        NearbyPlacesCache.clear(at: url)
        XCTAssertNil(NearbyPlacesCache.load(from: url))
    }
}
