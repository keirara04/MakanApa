import SwiftUI

/// The landing hero: a slow retro sunburst over halftone print dots, food polaroids bobbing
/// around the mascot, and two crossing dish-name ribbons along the bottom edge. All perpetual
/// motion runs off one TimelineView that pauses under Reduce Motion or in the background, and
/// the static layers (dots, rays, photos, ribbon text) are their own views so a tick only moves
/// them instead of redrawing them.
struct LoginHeroView: View {
    let height: CGFloat
    let mascotNamespace: Namespace.ID

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.scenePhase) private var scenePhase
    @State private var startedAt = Date()
    @State private var entered = false

    private static let focusY = 0.42

    private static let dishes = [
        "Nasi lemak", "Roti canai", "Teh tarik", "Char kuey teow",
        "Laksa", "Mee goreng", "Nasi kandar", "Ayam gepuk",
    ]

    private static let polaroids = [
        Polaroid(image: "MoodNasiLemak", x: 0.13, y: 0.19, size: 60, tilt: -9, phase: 0),
        Polaroid(image: "MoodCharKueyTeow", x: 0.87, y: 0.22, size: 54, tilt: 8, phase: 1.3),
        Polaroid(image: "MoodBananaLeafRice", x: 0.10, y: 0.60, size: 50, tilt: 7, phase: 2.1),
        Polaroid(image: "MoodAyamGepuk", x: 0.90, y: 0.61, size: 56, tilt: -6, phase: 3.4),
    ]

    var body: some View {
        GeometryReader { proxy in
            let size = proxy.size
            let focus = CGPoint(x: size.width / 2, y: size.height * Self.focusY)
            let mascotWidth = min(size.width * 0.62, 260, size.height * 0.92)
            let burstDiameter = hypot(size.width, size.height) * 1.15

            ZStack {
                HalftoneField(focusY: Self.focusY)

                TimelineView(.animation(minimumInterval: 1.0 / 60, paused: reduceMotion || scenePhase != .active)) { context in
                    let time = reduceMotion ? 0 : context.date.timeIntervalSince(startedAt)

                    ZStack {
                        Sunburst()
                            .frame(width: burstDiameter, height: burstDiameter)
                            .rotationEffect(.degrees(time * 4))
                            .scaleEffect(entered || reduceMotion ? 1 : 0.55)
                            .opacity(entered ? 1 : 0)
                            .animation(reduceMotion ? .easeOut(duration: 0.2) : .easeOut(duration: 1.1), value: entered)
                            .position(focus)
                            // Soft top and bottom edges, so the rays never end on a hard line
                            // under the status bar or above the headline.
                            .mask(LinearGradient(stops: [.init(color: .clear, location: 0),
                                                         .init(color: .black, location: 0.2),
                                                         .init(color: .black, location: 0.6),
                                                         .init(color: .clear, location: 1)],
                                                 startPoint: .top, endPoint: .bottom))

                        Circle()
                            .fill(RadialGradient(colors: [.white.opacity(0.85), .white.opacity(0)],
                                                 center: .center, startRadius: 8, endRadius: mascotWidth * 0.55))
                            .frame(width: mascotWidth * 1.1, height: mascotWidth * 1.1)
                            .position(focus)

                        ForEach(Array(Self.polaroids.enumerated()), id: \.offset) { index, polaroid in
                            FoodPolaroid(image: polaroid.image, size: polaroid.size)
                                .rotationEffect(.degrees(polaroid.tilt + sin(time * 0.9 + polaroid.phase) * 3))
                                .scaleEffect(entered || reduceMotion ? 1 : 0.2)
                                .opacity(entered ? 1 : 0)
                                .animation(
                                    reduceMotion
                                        ? .easeOut(duration: 0.2)
                                        : .spring(duration: 0.6, bounce: 0.45).delay(0.2 + Double(index) * 0.08),
                                    value: entered
                                )
                                .position(x: size.width * polaroid.x,
                                          y: size.height * polaroid.y + sin(time * 1.3 + polaroid.phase) * 5)
                        }

                        LoginMascotView(width: mascotWidth)
                            .matchedGeometryEffect(id: "mascot", in: mascotNamespace)
                            .offset(y: sin(time * 1.6) * 4)
                            .position(focus)

                        ribbons(width: size.width, time: time)
                            .position(x: size.width / 2, y: size.height - 34)
                    }
                }
            }
        }
        .frame(height: height)
        .clipped()
        .onAppear { entered = true }
    }

    /// Two bands crossing in an X, scrolling opposite ways — the back one slides in from the
    /// right, the front one from the left.
    private func ribbons(width: CGFloat, time: TimeInterval) -> some View {
        let bandWidth = width + 80
        return ZStack {
            LoginTickerRibbon(items: Self.dishes, fill: .kunyit, ink: .kicap, pointsPerSecond: 24, time: time)
                .frame(width: bandWidth)
                .rotationEffect(.degrees(3.5))
                .offset(x: entered || reduceMotion ? 0 : bandWidth)
                .animation(reduceMotion ? nil : .spring(duration: 0.8, bounce: 0.2).delay(0.35), value: entered)

            LoginTickerRibbon(items: Array(Self.dishes.reversed()), fill: .sambalRed, ink: .nasiCream, pointsPerSecond: -32, time: time)
                .frame(width: bandWidth)
                .rotationEffect(.degrees(-3))
                .shadow(color: Color.kicap.opacity(0.18), radius: 6, y: 3)
                .offset(x: entered || reduceMotion ? 0 : -bandWidth)
                .animation(reduceMotion ? nil : .spring(duration: 0.8, bounce: 0.2).delay(0.45), value: entered)
        }
    }
}

private struct Polaroid {
    let image: String
    /// Centre as a fraction of the hero's width/height.
    let x: CGFloat
    let y: CGFloat
    let size: CGFloat
    let tilt: Double
    /// Offsets the bob/sway so the photos never move in lockstep.
    let phase: Double
}

/// A dish photo in a white instant-film frame, chin and all.
private struct FoodPolaroid: View {
    let image: String
    let size: CGFloat

    var body: some View {
        Image(image)
            .resizable()
            .scaledToFill()
            .frame(width: size, height: size)
            .clipShape(RoundedRectangle(cornerRadius: 6))
            .padding(4)
            .padding(.bottom, 10)
            .background(.white, in: RoundedRectangle(cornerRadius: 9))
            .shadow(color: Color.kicap.opacity(0.16), radius: 8, y: 4)
            .accessibilityHidden(true)
    }
}

/// Alternating rays fading out from the centre. Drawn once; the hero rotates the whole view.
private struct Sunburst: View {
    var body: some View {
        Canvas { context, size in
            let center = CGPoint(x: size.width / 2, y: size.height / 2)
            let radius = size.width / 2
            let rays = 20
            let step = 2 * Double.pi / Double(rays)
            var path = Path()
            for ray in 0..<rays {
                let start = Double(ray) * step
                path.move(to: center)
                path.addLine(to: CGPoint(x: center.x + cos(start) * radius, y: center.y + sin(start) * radius))
                path.addLine(to: CGPoint(x: center.x + cos(start + step / 2) * radius,
                                         y: center.y + sin(start + step / 2) * radius))
                path.closeSubpath()
            }
            context.fill(path, with: .color(Color.kunyit.opacity(0.3)))
        }
        .mask(EllipticalGradient(colors: [.black, .black.opacity(0.35), .clear],
                                 center: .center, startRadiusFraction: 0, endRadiusFraction: 0.5))
        .accessibilityHidden(true)
    }
}

/// Retro print-style dots that grow toward the edges and fade out downward.
private struct HalftoneField: View {
    let focusY: CGFloat

    var body: some View {
        Canvas { context, size in
            let spacing: CGFloat = 14
            let center = CGPoint(x: size.width / 2, y: size.height * focusY)
            let reach = hypot(size.width, size.height) / 2
            var dots = Path()
            var row = 0
            var y: CGFloat = 0
            while y <= size.height {
                var x: CGFloat = row.isMultiple(of: 2) ? 0 : spacing / 2
                while x <= size.width {
                    let distance = hypot(x - center.x, y - center.y) / reach
                    let radius = (distance - 0.3) * 4
                    if radius > 0.4 {
                        dots.addEllipse(in: CGRect(x: x - radius, y: y - radius, width: radius * 2, height: radius * 2))
                    }
                    x += spacing
                }
                y += spacing * 0.866
                row += 1
            }
            context.fill(dots, with: .color(Color.sambalRed.opacity(0.1)))
        }
        .mask(LinearGradient(colors: [.black, .black.opacity(0.6), .clear], startPoint: .top, endPoint: .bottom))
        .accessibilityHidden(true)
    }
}
