import SwiftUI

/// Deterministic, locally drawn grain keeps the preview and high-resolution export identical.
struct AmbassadorCardPaper: View {
    var body: some View {
        Canvas { context, size in
            var seed: UInt64 = 0x4D414B414E415041
            func sample() -> CGFloat {
                seed = seed &* 6_364_136_223_846_793_005 &+ 1_442_695_040_888_963_407
                return CGFloat(seed >> 40) / CGFloat(1 << 24)
            }

            let count = Int(size.width * size.height / 48)
            for index in 0..<count {
                let point = CGPoint(x: sample() * size.width, y: sample() * size.height)
                let diameter = 0.2 + sample() * 0.45
                let grain = Path(ellipseIn: CGRect(x: point.x, y: point.y, width: diameter, height: diameter))
                context.fill(grain, with: .color(index.isMultiple(of: 3) ? .white.opacity(0.22) : Color.kicap.opacity(0.045)))
            }

            let corners: [(CGPoint, CGFloat)] = [
                (CGPoint(x: 30, y: 34), 8),
                (CGPoint(x: size.width - 32, y: 36), 11),
                (CGPoint(x: 32, y: size.height - 36), 10),
                (CGPoint(x: size.width - 30, y: size.height - 34), 7)
            ]
            for (point, radius) in corners {
                context.fill(sparkle(at: point, radius: radius), with: .color(Color.kunyit.opacity(0.8)))
                let glint = CGPoint(x: point.x + (point.x < size.width / 2 ? 12 : -13), y: point.y + 14)
                context.fill(sparkle(at: glint, radius: 3), with: .color(Color.kunyit.opacity(0.5)))
            }
        }
        .background(Color.nasiCream)
        .accessibilityHidden(true)
    }

    private func sparkle(at point: CGPoint, radius: CGFloat) -> Path {
        Path { path in
            path.move(to: CGPoint(x: point.x, y: point.y - radius))
            path.addQuadCurve(to: CGPoint(x: point.x + radius, y: point.y), control: point)
            path.addQuadCurve(to: CGPoint(x: point.x, y: point.y + radius), control: point)
            path.addQuadCurve(to: CGPoint(x: point.x - radius, y: point.y), control: point)
            path.addQuadCurve(to: CGPoint(x: point.x, y: point.y - radius), control: point)
            path.closeSubpath()
        }
    }
}
