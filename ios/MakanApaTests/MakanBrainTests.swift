import XCTest
@testable import MakanApa

/// Pins the iOS Makan Brain models to the backend's BrainPresenter / TuneService / SeleraTraits payloads.
final class MakanBrainTests: XCTestCase {
    private func recommendationJSON(extra: String) -> String {
        """
        {"id":1,"name":"Nasi Ayam Bangi","headline":"NASI AYAM.","foodCategory":null,"latitude":2.9,"longitude":101.7,
         "distanceKm":0.4,"rating":4.6,"priceLevel":1,"cuisines":["malay"],"openStatus":"open","photos":[],"reviews":[],
         "menuItems":[],"placeGoogleMapsUrl":null,"closesAt":null,"communityTag":null\(extra)}
        """
    }

    func testV1RecommendationDecodesWithoutBrainKeys() throws {
        let pick = try JSONDecoder().decode(RecommendationResponse.Recommendation.self, from: Data(recommendationJSON(extra: "").utf8))

        XCTAssertNil(pick.reasons)
        XCTAssertNil(pick.fit)
        XCTAssertNil(pick.canTune)
    }

    func testBrainRecommendationDecodes() throws {
        let extra = """
        ,"reasons":[{"family":"edge","key":"close","icon":"📍","text":"400 m — closest one that matched"}],
         "decidingFactor":"Deciding factor: closest strong match.","fit":"strong",
         "thinkingTrace":["Checked 31 nearby spots","Picked this one"],
         "context":[{"key":"rain","label":"Hujan","icon":"☔","active":true,"ignored":false,"confidence":0.8,"stale":false}],
         "hasWhatIf":true,"canTune":true,"fatigue":false
        """
        let pick = try JSONDecoder().decode(RecommendationResponse.Recommendation.self, from: Data(recommendationJSON(extra: extra).utf8))

        XCTAssertEqual(pick.fit, .strong)
        XCTAssertEqual(pick.reasons?.first?.family, "edge")
        XCTAssertEqual(pick.thinkingTrace?.last, "Picked this one")
        XCTAssertEqual(pick.context?.first?.key, "rain")
        XCTAssertEqual(pick.hasWhatIf, true)
    }

    func testTuneSearchWiderDecodes() throws {
        let json = #"{"recommendation":null,"canSearchWider":true,"message":"Nothing better","suggestedAdjustment":{"distanceKm":3.5,"budgetMax":null}}"#
        let response = try JSONDecoder().decode(TuneResponse.self, from: Data(json.utf8))

        XCTAssertNil(response.recommendation)
        XCTAssertEqual(response.suggestedAdjustment?.distanceKm, 3.5)
    }

    func testSeleraDecodes() throws {
        let json = """
        {"stage":"learning","signalCount":4,
         "traits":[{"key":"category:nasi","icon":"🍚","label":"Nasi person","strength":"medium","evidence":"3 of your last 4 accepted picks were nasi"}],
         "constraints":[{"key":"halal","label":"Halal only","icon":"✅","value":true,"editIn":"settings"},
                        {"key":"budget","label":"Budget","icon":"💰","value":null,"editIn":"each_decision"}]}
        """
        let selera = try JSONDecoder().decode(SeleraResponse.self, from: Data(json.utf8))

        XCTAssertEqual(selera.traits.first?.key, "category:nasi")
        XCTAssertNil(selera.constraints[1].value)
    }

    func testRequestBodyCarriesLensAndIgnoredContext() throws {
        let body = SoloRecommendationRequestBody(
            latitude: 1, longitude: 2, budgetMax: nil, maxDistanceKm: 2, moods: [], craving: nil,
            mode: nil, vibe: nil, installationId: nil, halal: false, lens: .quickOne, ignoreContext: ["rain"]
        )
        let json = try XCTUnwrap(JSONSerialization.jsonObject(with: JSONEncoder().encode(body)) as? [String: Any])

        XCTAssertEqual(json["lens"] as? String, "quick_one")
        XCTAssertEqual(json["ignoreContext"] as? [String], ["rain"])
    }

    func testContextPreferencesToggleRoundTrips() {
        let saved = ContextPreferences.ignoredKeys
        defer { ContextPreferences.ignoredKeys = saved }
        ContextPreferences.ignoredKeys = []

        ContextPreferences.toggle("rain")
        XCTAssertTrue(ContextPreferences.isIgnored("rain"))
        ContextPreferences.toggle("rain")
        XCTAssertFalse(ContextPreferences.isIgnored("rain"))
    }
}
