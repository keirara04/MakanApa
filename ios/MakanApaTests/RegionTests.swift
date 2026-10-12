import CoreLocation
import XCTest
@testable import MakanApa

/// Pins the iOS Region to api/app/Support/Region.php (same boxes, same price words).
final class RegionTests: XCTestCase {
    func testPointsResolveToTheirRegion() {
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 2.9284, longitude: 101.7802)), .malaysia)
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 37.5894, longitude: 127.0276)), .korea)
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 40.7982, longitude: -77.8599)), .unitedStates)
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 51.5072, longitude: -0.1276)), .other)
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 1.2903, longitude: 103.852)), .other) // Singapore
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 43.6532, longitude: -79.3832)), .other) // Toronto
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 49.2827, longitude: -123.1207)), .other) // Vancouver
        XCTAssertEqual(Region.at(CLLocationCoordinate2D(latitude: 42.8864, longitude: -78.8784)), .unitedStates) // Buffalo
    }

    func testTimeZoneGuess() {
        XCTAssertEqual(Region.fromTimeZone("Asia/Seoul"), .korea)
        XCTAssertEqual(Region.fromTimeZone("America/Indiana/Indianapolis"), .unitedStates)
        XCTAssertEqual(Region.fromTimeZone("America/Toronto"), .other)
        XCTAssertEqual(Region.fromTimeZone("Asia/Kuala_Lumpur"), .malaysia)
    }

    func testLabels() {
        XCTAssertEqual(Region.malaysia.approximateSpend(2), "≈ RM20/person")
        XCTAssertEqual(Region.korea.approximateSpend(3), "≈ ₩30,000+/person")
        XCTAssertEqual(Region.other.approximateSpend(1), "Budget")
        XCTAssertEqual(Region.korea.price(12000), "₩12,000")
        XCTAssertEqual(Region.malaysia.price(12.5), "RM12.50")
        XCTAssertEqual(Region.unitedStates.distance(km: 1.6, kmText: "1.6 km"), "1.0 mi")
        XCTAssertEqual(Region.malaysia.distance(km: 1.6, kmText: "1.6 km"), "1.6 km")
    }

    func testMalaysiaMoodsUnchangedAndAbroadMoodsAreCuisines() {
        XCTAssertEqual(SoloViewModel.moodOptions(for: .malaysia).first?.tag, "nasi_kandar")
        XCTAssertEqual(SoloViewModel.moodOptions(for: .korea).map(\.tag),
                       ["middle_eastern", "indian", "malay", "chicken", "korean", "burger", "healthy", "quick"])
        XCTAssertEqual(SoloViewModel.moodOptions(for: .unitedStates).first?.label, "Halal cart")
    }
}
