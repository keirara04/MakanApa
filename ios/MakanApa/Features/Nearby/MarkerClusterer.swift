import CoreGraphics
import Foundation

/// One rating pill the clusterer considers, already projected to screen points by the caller —
/// `NearbyMapView` uses the map's own projection, tests feed synthetic points. `point` is the
/// marker's ground anchor (bottom-centre of the pill, Google's default); `size` is its bitmap.
struct ClusterInput: Equatable {
    let placeId: Int
    let rating: Double?
    let point: CGPoint
    let size: CGSize
}

/// Rating pills that would overlap on screen, drawn as one count bubble instead.
struct PlaceCluster: Equatable {
    /// Built from the sorted member ids, so a recluster that keeps the same group reuses its
    /// marker instead of popping a new one.
    let id: String
    /// Best-rated first — the order a fan-out lays the pills out in.
    let memberIds: [Int]
    /// Mean of the members' points, in the same space as the inputs.
    let point: CGPoint
    /// Members still collide at `MarkerClusterer.maxExpansionZoom` — zooming in can't pull them
    /// apart (food court stalls, one mall), so a tap fans them out instead.
    let isStacked: Bool
}

/// Screen-space clustering for the Nearby map's rating pills. Pure geometry, no map SDK — pills
/// whose boxes touch are linked, and every connected group of two or more becomes a cluster.
enum MarkerClusterer {
    /// Breathing room kept between any two markers — pills that only just miss each other still
    /// read as one smudge.
    static let collisionPadding: CGFloat = 4

    /// Deepest zoom a cluster tap zooms to (street level). A group that still collides here is
    /// stacked.
    static let maxExpansionZoom: Float = 19

    static func clusters(
        _ inputs: [ClusterInput], zoom: Float, bubbleSize: (Int) -> CGSize
    ) -> [PlaceCluster] {
        let inputs = inputs.sorted { $0.placeId < $1.placeId }
        var groups = components(inputs.map { frame(at: $0.point, size: $0.size) })
        mergeCollidingBubbles(&groups, inputs: inputs, bubbleSize: bubbleSize)

        return groups.filter { $0.count > 1 }.map { indices in
            let members = indices.map { inputs[$0] }
            let ordered = members.sorted {
                ($0.rating ?? -1, -$0.placeId) > ($1.rating ?? -1, -$1.placeId)
            }
            return PlaceCluster(
                id: "cluster:" + members.map { String($0.placeId) }.joined(separator: "-"),
                memberIds: ordered.map(\.placeId),
                point: centroid(members.map(\.point)),
                isStacked: isStacked(members, zoom: zoom)
            )
        }
        .sorted { $0.id < $1.id }
    }

    /// Where each stacked pill's centre goes, relative to the stack's spot, when a tap fans it
    /// out: evenly spaced rings, spaced by a padded pill's diagonal so no two pills — on one
    /// ring or on neighbouring rings — can touch. A big food court spills into wider rings.
    static func fanOffsets(for sizes: [CGSize]) -> [CGPoint] {
        guard let widest = sizes.map(\.width).max(), let tallest = sizes.map(\.height).max() else { return [] }
        let spacing = hypot(widest + collisionPadding, tallest + collisionPadding)

        var offsets: [CGPoint] = []
        var ring = 1
        while offsets.count < sizes.count {
            let radius = spacing * CGFloat(ring)
            let capacity = Int((2 * .pi * CGFloat(ring)).rounded(.down))
            let count = min(capacity, sizes.count - offsets.count)
            // Two pills sit side by side; more start at 12 o'clock and go clockwise.
            let start: CGFloat = count == 2 ? .pi : -.pi / 2
            for index in 0..<count {
                let angle = start + 2 * .pi * CGFloat(index) / CGFloat(count)
                offsets.append(CGPoint(x: radius * cos(angle), y: radius * sin(angle)))
            }
            ring += 1
        }
        return offsets
    }

    /// A marker's padded box — bottom-anchored like every marker on this map.
    static func frame(at point: CGPoint, size: CGSize) -> CGRect {
        CGRect(x: point.x - size.width / 2, y: point.y - size.height, width: size.width, height: size.height)
            .insetBy(dx: -collisionPadding / 2, dy: -collisionPadding / 2)
    }

    /// Connected components of the overlap graph — A↔B and B↔C land in one group even when A
    /// and C don't touch, so a chain of pills never splits into groups that still overlap.
    private static func components(_ frames: [CGRect]) -> [[Int]] {
        var parent = Array(frames.indices)
        func root(_ index: Int) -> Int {
            var index = index
            while parent[index] != index {
                parent[index] = parent[parent[index]]
                index = parent[index]
            }
            return index
        }
        for i in frames.indices {
            for j in frames.indices where j > i && frames[i].intersects(frames[j]) {
                parent[root(j)] = root(i)
            }
        }
        return Dictionary(grouping: frames.indices, by: root).values
            .map { $0.sorted() }
            .sorted { $0[0] < $1[0] }
    }

    /// A count bubble sits at its members' centroid and is bigger than a pill, so it can land on
    /// a neighbouring pill or bubble the overlap graph never linked — fold those in until nothing
    /// a bubble draws over is left standing.
    private static func mergeCollidingBubbles(
        _ groups: inout [[Int]], inputs: [ClusterInput], bubbleSize: (Int) -> CGSize
    ) {
        func frame(of group: [Int]) -> CGRect {
            if group.count == 1 {
                return Self.frame(at: inputs[group[0]].point, size: inputs[group[0]].size)
            }
            return Self.frame(at: centroid(group.map { inputs[$0].point }), size: bubbleSize(group.count))
        }

        var didMerge = true
        while didMerge {
            didMerge = false
            let frames = groups.map(frame(of:))
            search: for i in groups.indices {
                for j in groups.indices where j > i
                    && (groups[i].count > 1 || groups[j].count > 1)
                    && frames[i].intersects(frames[j]) {
                    groups[i] = (groups[i] + groups[j]).sorted()
                    groups.remove(at: j)
                    didMerge = true
                    break search
                }
            }
        }
    }

    /// Replays the overlap graph as if the camera were at `maxExpansionZoom` — screen distances
    /// double per zoom level while pills stay the same size. Still one group there means zooming
    /// in won't help.
    private static func isStacked(_ members: [ClusterInput], zoom: Float) -> Bool {
        let scale = CGFloat(pow(2, Double(max(0, maxExpansionZoom - zoom))))
        let frames = members.map {
            frame(at: CGPoint(x: $0.point.x * scale, y: $0.point.y * scale), size: $0.size)
        }
        return components(frames).count == 1
    }

    private static func centroid(_ points: [CGPoint]) -> CGPoint {
        let sum = points.reduce(CGPoint.zero) { CGPoint(x: $0.x + $1.x, y: $0.y + $1.y) }
        return CGPoint(x: sum.x / CGFloat(points.count), y: sum.y / CGFloat(points.count))
    }
}
