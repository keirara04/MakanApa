import SwiftUI

/// A continuous, time-based loop isolated from the rest of the loading screen.
struct AnimatedMakanMascot: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.scenePhase) private var scenePhase
    @State private var startedAt = Date()

    var body: some View {
        TimelineView(.animation(minimumInterval: 1.0 / 30, paused: reduceMotion || scenePhase != .active)) { context in
            let time = reduceMotion ? 0 : context.date.timeIntervalSince(startedAt)
            let bounce = reduceMotion ? 0 : (1 - cos(time * .pi)) / 2

            ZStack {
                Circle()
                    .fill(.white.opacity(0.55))

                if !reduceMotion {
                    // A soft search ripple expands behind the character, then fades.
                    let ripple = time.truncatingRemainder(dividingBy: 2.8) / 2.8
                    Circle()
                        .stroke(Color.pandan.opacity(0.18 * sin(ripple * .pi)), lineWidth: 1.5)
                        .scaleEffect(0.76 + ripple * 0.32)

                    Circle()
                        .trim(from: 0, to: 0.16)
                        .stroke(Color.pandan.opacity(0.5), style: StrokeStyle(lineWidth: 2.5, lineCap: .round))
                        .rotationEffect(.degrees(time * 60 - 90))
                        .padding(2)
                }

                Ellipse()
                    .fill(Color.kicap.opacity(0.07 - bounce * 0.025))
                    .frame(width: 64 - bounce * 12, height: 8 - bounce * 2)
                    .blur(radius: 2)
                    .offset(y: 72)

                ZStack {
                    Image("MascotThinking")
                        .resizable()
                        .scaledToFit()
                        .frame(width: 168, height: 168)

                    if !reduceMotion {
                        Canvas { canvas, _ in
                            // Wisps rise from the bowl in staggered, seamless cycles.
                            for index in 0..<3 {
                                let phase = (time / 2.4 + Double(index) / 3)
                                    .truncatingRemainder(dividingBy: 1)
                                let x = 82.0 + Double(index - 1) * 9 + sin(phase * .pi * 2) * 3
                                let y = 118.0 - phase * 36
                                var wisp = Path()
                                wisp.move(to: CGPoint(x: x, y: y))
                                wisp.addCurve(
                                    to: CGPoint(x: x + 2, y: y - 16),
                                    control1: CGPoint(x: x - 7, y: y - 5),
                                    control2: CGPoint(x: x + 8, y: y - 11)
                                )
                                canvas.stroke(
                                    wisp,
                                    with: .color(Color.kicap.opacity(sin(phase * .pi) * 0.22)),
                                    style: StrokeStyle(lineWidth: 2, lineCap: .round)
                                )
                            }
                        }
                        .frame(width: 168, height: 168)
                    }
                }
                .rotationEffect(.degrees(reduceMotion ? 0 : sin(time * .pi) * 3), anchor: .bottom)
                .offset(y: -bounce * 9)
            }
            .frame(width: 216, height: 216)
        }
        .accessibilityHidden(true)
        .onAppear { startedAt = Date() }
    }
}

#Preview {
    AnimatedMakanMascot()
        .padding(40)
        .background(Color.nasiCream)
}
