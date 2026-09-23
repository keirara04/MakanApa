import XCTest
@testable import MakanApa

/// Every entry point — shared universal links, the makanapa:// scheme from the share page, and
/// push payloads — must parse to the same typed destinations.
final class DeepLinkTests: XCTestCase {
    func testSharedUniversalLinkOpensThePlace() {
        let url = URL(string: "https://makanapa.hakeemiridza.com/p/624-kfc-jalan-reko?ref=share")!
        XCTAssertEqual(DeepLinkDestination(url: url), .place(id: 624, source: .share))
    }

    func testBareIdLinkWorksWithoutASlug() {
        let url = URL(string: "https://makanapa.hakeemiridza.com/p/624")!
        XCTAssertEqual(DeepLinkDestination(url: url), .place(id: 624, source: .share))
    }

    func testAppSchemeFromTheSharePage() {
        let url = URL(string: "makanapa://place/624?source=share")!
        XCTAssertEqual(DeepLinkDestination(url: url), .place(id: 624, source: .share))
    }

    func testReservedGengRoomPath() {
        let url = URL(string: "https://makanapa.hakeemiridza.com/g/ABC123")!
        XCTAssertEqual(DeepLinkDestination(url: url), .gengRoom(code: "ABC123"))
    }

    func testUnrelatedUrlsAreLeftForOtherHandlers() {
        XCTAssertNil(DeepLinkDestination(url: URL(string: "com.googleusercontent.apps.123:/oauth2redirect?code=x")!))
        XCTAssertNil(DeepLinkDestination(url: URL(string: "https://makanapa.hakeemiridza.com/privacy")!))
        XCTAssertNil(DeepLinkDestination(url: URL(string: "https://makanapa.hakeemiridza.com/p/not-a-number")!))
    }

    func testNamedAndGenericMealNudgePushes() {
        XCTAssertEqual(
            DeepLinkDestination(userInfo: ["type": "meal_nudge", "nudgeId": NSNumber(value: 9), "restaurantId": NSNumber(value: 624)]),
            .mealNudge(nudgeId: 9, restaurantId: 624)
        )
        XCTAssertEqual(
            DeepLinkDestination(userInfo: ["type": "meal_nudge", "nudgeId": NSNumber(value: 9)]),
            .mealNudge(nudgeId: 9, restaurantId: nil)
        )
    }

    func testPreferencesFromAnOlderBackendDefaultMealtimePicksOff() throws {
        let json = #"{"community_submissions":true,"account_admin":true,"release_announcements":false}"#
        let preferences = try JSONDecoder().decode(NotificationPreferences.self, from: Data(json.utf8))
        XCTAssertFalse(preferences.mealtimeNudges)
    }
}
