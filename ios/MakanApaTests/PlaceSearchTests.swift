import XCTest
@testable import MakanApa

/// Pins the search models to PlaceSearchController's payload (api/app/Http/Controllers/Api/
/// PlaceSearchController.php) — canonical keys, the legacy aliases older backends sent — and
/// the SearchSession helpers the results list and place sheet rely on.
final class PlaceSearchTests: XCTestCase {
    private let responseJSON = """
    {"results":[
      {"provenance":"canonical","id":1,"googlePlaceId":null,"name":"KFC","address":"Jalan Reko, Kajang",
       "category":"Fast Food","cuisine":null,"latitude":2.93,"longitude":101.78,"priceLevel":2,"rating":3.9,
       "distanceKm":1.2,"openStatus":"open","closesAt":"10:00 PM",
       "halal":{"status":"unknown","display":{"shortLabel":"Help verify","longLabel":"Halal status unknown","tone":"neutral","action":"help_verify"}},
       "isCommunityFind":false,"groupKey":"kfc","groupSize":2,
       "restaurantId":1,"foodCategory":"fast_food","signatureDish":null,"cuisines":[]},
      {"provenance":"canonical","id":2,"name":"KFC","address":"Bangi Gateway","category":"Fast Food",
       "latitude":2.95,"longitude":101.79,"priceLevel":2,"rating":4.0,"distanceKm":3.4,"openStatus":"closed",
       "isCommunityFind":false,"groupKey":"kfc","groupSize":2},
      {"provenance":"google_fallback","id":null,"googlePlaceId":"g-1","name":"Kedai Kopi","latitude":2.9,
       "longitude":101.7,"openStatus":"unknown","isCommunityFind":false,"groupKey":null,"groupSize":1}
     ],
     "meta":{"radiusKm":6,"source":"mixed","googleAvailable":false,"widerRadiusKm":12,"total":3},
     "suggestions":[]}
    """

    private func decodeResponse() throws -> PlaceSearchResponseV2 {
        try JSONDecoder().decode(PlaceSearchResponseV2.self, from: Data(responseJSON.utf8))
    }

    private func session(selected: String? = nil) throws -> SearchSession {
        let response = try decodeResponse()
        return SearchSession(
            query: "kfc", centerLatitude: 2.93, centerLongitude: 101.78, radiusKm: 6,
            results: response.results, meta: response.meta, suggestions: [],
            selectedResultId: selected, searchedAt: Date()
        )
    }

    func testCanonicalPayloadDecodes() throws {
        let response = try decodeResponse()
        let first = try XCTUnwrap(response.results.first)

        XCTAssertEqual(first.restaurantId, 1)
        XCTAssertEqual(first.address, "Jalan Reko, Kajang")
        XCTAssertEqual(first.closesAt, "10:00 PM")
        XCTAssertEqual(first.halal?.status, .unknown)
        XCTAssertEqual(response.meta?.widerRadiusKm, 12)
        XCTAssertNil(response.results[2].restaurantId)
        XCTAssertEqual(response.results[2].id, "g-1")
    }

    func testLegacyPayloadStillDecodes() throws {
        // What the backend sent before the canonical keys: restaurantId, no openStatus/meta.
        let legacy = """
        {"results":[{"provenance":"community","restaurantId":7,"googlePlaceId":null,"name":"Warung",
          "foodCategory":null,"signatureDish":null,"cuisines":[],"latitude":2.9,"longitude":101.7,
          "priceLevel":null,"rating":null,"distanceKm":0.4,"isCommunityFind":true}]}
        """
        let response = try JSONDecoder().decode(PlaceSearchResponseV2.self, from: Data(legacy.utf8))
        let result = try XCTUnwrap(response.results.first)

        XCTAssertEqual(result.restaurantId, 7)
        XCTAssertEqual(result.openStatus, "unknown")
        XCTAssertEqual(result.groupSize, 1)
        XCTAssertTrue(result.isCommunityFind)
        XCTAssertNil(response.meta)
    }

    func testSearchResultBecomesASheetReadyPlace() throws {
        let place = try decodeResponse().results[0].asNearbyPlace(id: 1)

        XCTAssertEqual(place.openStatus, "open")
        XCTAssertEqual(place.halal?.status, .unknown)
    }

    func testBranchGroupCueAndOtherBranches() throws {
        let session = try session()

        XCTAssertTrue(session.startsGroup(at: 0))
        XCTAssertFalse(session.startsGroup(at: 1))
        XCTAssertFalse(session.startsGroup(at: 2))
        XCTAssertEqual(session.otherBranches(of: session.results[0]).map(\.restaurantId), [2])
        XCTAssertTrue(session.otherBranches(of: session.results[2]).isEmpty)
    }

    func testSelectionSurvivesPresentationChanges() throws {
        var session = try session(selected: "2")
        session.presentation = .map
        session.presentation = .list

        XCTAssertEqual(session.selectedResult?.restaurantId, 2)
        XCTAssertEqual(session.source, "mixed")
    }
}
