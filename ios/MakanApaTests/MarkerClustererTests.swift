import XCTest
@testable import MakanApa

/// Pins the Nearby map's screen-space clustering (MarkerClusterer) — which pills fold into a
/// count bubble, when a bubble is "stacked" (fans out instead of zooming), and that a fan-out
/// never lays two pills on top of each other.
final class MarkerClustererTests: XCTestCase {
    private let pill = CGSize(width: 48, height: 22)
    private let bubble = CGSize(width: 34, height: 34)

    private func input(_ id: Int, x: CGFloat, y: CGFloat = 0, rating: Double = 4.0) -> ClusterInput {
        ClusterInput(placeId: id, rating: rating, point: CGPoint(x: x, y: y), size: pill)
    }

    private func clusters(_ inputs: [ClusterInput], zoom: Float = 15) -> [PlaceCluster] {
        MarkerClusterer.clusters(inputs, zoom: zoom) { _ in self.bubble }
    }

    func testFarApartPillsStayStandalone() {
        XCTAssertEqual(clusters([input(1, x: 0), input(2, x: 200), input(3, x: 400)]), [])
    }

    func testOverlappingPillsFoldIntoOneCluster() {
        let result = clusters([input(1, x: 0), input(2, x: 20)])

        XCTAssertEqual(result.count, 1)
        XCTAssertEqual(Set(result[0].memberIds), [1, 2])
        XCTAssertEqual(result[0].point, CGPoint(x: 10, y: 0))
    }

    func testOverlapChainIsOneCluster() {
        // A↔B and B↔C touch, A and C don't — still one group, never [A, B] + [C].
        let result = clusters([input(1, x: 0), input(2, x: 40), input(3, x: 80)])

        XCTAssertEqual(result.count, 1)
        XCTAssertEqual(Set(result[0].memberIds), [1, 2, 3])
    }

    func testBubbleLandingOnNeighbourPillMergesIt() {
        // 1 and 2 overlap; 3 sits just above both pills without touching either, but their
        // bubble — taller than a pill, centred between them — reaches up onto it.
        let wideBubble = CGSize(width: 40, height: 34)
        let inputs = [input(1, x: 0), input(2, x: 44), input(3, x: 30, y: -28)]

        let result = MarkerClusterer.clusters(inputs, zoom: 15) { _ in wideBubble }

        XCTAssertEqual(result.count, 1)
        XCTAssertEqual(Set(result[0].memberIds), [1, 2, 3])
    }

    func testMembersOrderedBestRatedFirst() {
        let result = clusters([input(1, x: 0, rating: 3.9), input(2, x: 10, rating: 4.8), input(3, x: 20, rating: 3.9)])

        XCTAssertEqual(result[0].memberIds, [2, 1, 3])
    }

    func testClusterIdIsStableAcrossInputOrderAndZoom() {
        let forward = clusters([input(7, x: 0), input(3, x: 10)], zoom: 15)
        let reversed = clusters([input(3, x: 10), input(7, x: 0)], zoom: 15.3)

        XCTAssertEqual(forward.map(\.id), ["cluster:3-7"])
        XCTAssertEqual(forward.map(\.id), reversed.map(\.id))
    }

    func testClusterThatSplitsWhenZoomedInIsNotStacked() {
        // 30pt apart at zoom 15 is 480pt apart at zoom 19 — zooming in pulls them apart.
        XCTAssertEqual(clusters([input(1, x: 0), input(2, x: 30)], zoom: 15).first?.isStacked, false)
    }

    func testSameSpotIsStacked() {
        let result = clusters([input(1, x: 100, y: 100), input(2, x: 100, y: 100), input(3, x: 100.5, y: 100)])

        XCTAssertEqual(result.first?.isStacked, true)
    }

    func testAnyClusterAtMaxExpansionZoomIsStacked() {
        XCTAssertEqual(clusters([input(1, x: 0), input(2, x: 30)], zoom: MarkerClusterer.maxExpansionZoom).first?.isStacked, true)
    }

    func testFanOffsetsNeverOverlap() {
        for count in [2, 3, 6, 7, 20] {
            let sizes = Array(repeating: pill, count: count)
            let frames = MarkerClusterer.fanOffsets(for: sizes).map {
                CGRect(x: $0.x - pill.width / 2, y: $0.y - pill.height / 2, width: pill.width, height: pill.height)
            }

            XCTAssertEqual(frames.count, count)
            for i in frames.indices {
                for j in frames.indices where j > i {
                    XCTAssertFalse(frames[i].intersects(frames[j]), "fan of \(count): \(i) overlaps \(j)")
                }
            }
        }
    }

    func testFanOfTwoSitsSideBySide() {
        let offsets = MarkerClusterer.fanOffsets(for: [pill, pill])

        XCTAssertEqual(offsets[0].y, 0, accuracy: 0.001)
        XCTAssertEqual(offsets[1].y, 0, accuracy: 0.001)
        XCTAssertLessThan(offsets[0].x, offsets[1].x)
    }
}
