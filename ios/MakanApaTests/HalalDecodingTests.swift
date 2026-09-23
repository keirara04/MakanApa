import XCTest
@testable import MakanApa

/// Pins the iOS models to the backend's HalalPresenter payload shape (api/app/Services/Halal/HalalPresenter.php).
final class HalalDecodingTests: XCTestCase {
    func testFullHalalPayloadDecodes() throws {
        let json = """
        {"status":"certified","reviewState":"clear",
         "display":{"shortLabel":"Halal (JAKIM)","longLabel":"JAKIM halal certified","tone":"certified","action":null,
                    "verificationLabel":"Verified from community evidence · Reviewed 23 Sep 2026"},
         "verification":{"method":"moderator_review","evidenceSource":"community","authority":"jakim",
                         "verifiedAt":"2026-09-23T03:20:00+00:00","expiresAt":"2027-03-01","registryCheckedAt":null},
         "reports":[{"id":7,"claim":"certified","resolvedStatus":"certified","isCurrent":true,"comment":"Cert by cashier",
                     "userName":"Hakeem","approvedAt":"2026-09-23T03:20:00+00:00",
                     "photos":[{"id":1,"url":"https://x/y.jpg","photoType":"halal_cert"}]}],
         "historyCount":2}
        """
        let info = try JSONDecoder().decode(HalalInfo.self, from: Data(json.utf8))

        XCTAssertEqual(info.status, .certified)
        XCTAssertEqual(info.display.shortLabel, "Halal (JAKIM)")
        XCTAssertFalse(info.display.invitesReport)
        XCTAssertEqual(info.verification?.authority, .jakim)
        XCTAssertTrue(info.reports[0].isCurrent)
    }

    func testMarkerSummaryWithoutVerificationLabelDecodes() throws {
        let json = """
        {"status":"unknown","display":{"shortLabel":"Not verified · Help verify","longLabel":"x","tone":"neutral","action":"help_verify"}}
        """
        let summary = try JSONDecoder().decode(HalalSummary.self, from: Data(json.utf8))

        XCTAssertEqual(summary.status, .unknown)
        XCTAssertTrue(summary.display.invitesReport)
        XCTAssertNil(summary.display.verificationLabel)
    }

    func testDateHelpersRoundTrip() {
        XCTAssertEqual(HalalDates.apiDate(HalalDates.parseForTests("2027-03-01")!), "2027-03-01")
        XCTAssertNotEqual(HalalDates.display("2027-03-01"), "2027-03-01")
    }
}
