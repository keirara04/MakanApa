import XCTest
@testable import MakanApa

final class CravingSelectionSerializationTests: XCTestCase {
    func testTagSelectionSerializesToMoodsOnly() {
        let viewModel = SoloViewModel()
        viewModel.cravingSelection = .tag("nasi_kandar")

        XCTAssertEqual(viewModel.outgoingMoods, ["nasi_kandar"])
        XCTAssertNil(viewModel.outgoingCraving)
    }

    func testCustomSelectionTrimsAndCollapsesWhitespace() {
        let viewModel = SoloViewModel()
        viewModel.cravingSelection = .custom("  nasi   kandar  ")

        XCTAssertEqual(viewModel.outgoingMoods, [])
        XCTAssertEqual(viewModel.outgoingCraving, "nasi kandar")
    }

    func testAnythingSelectionSerializesToNoPreference() {
        let viewModel = SoloViewModel()
        viewModel.cravingSelection = .anything

        XCTAssertEqual(viewModel.outgoingMoods, [])
        XCTAssertNil(viewModel.outgoingCraving)
    }

    func testWhitespaceOnlyCustomTextSerializesToNilCraving() {
        let viewModel = SoloViewModel()
        viewModel.cravingSelection = .custom("   ")

        XCTAssertEqual(viewModel.outgoingMoods, [])
        XCTAssertNil(viewModel.outgoingCraving)
    }
}
