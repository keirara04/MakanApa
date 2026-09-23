import SwiftUI
import UIKit

/// The mascot + wordmark with a short hop on arrival, replayable by touch. The hero's sunburst
/// sits behind it, so it carries no backdrop of its own.
struct LoginMascotView: View {
    let width: CGFloat
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var replay = 0

    var body: some View {
        Button {
            replay += 1
            UIImpactFeedbackGenerator(style: .soft).impactOccurred()
        } label: {
            Image("MascotCelebrate")
                .resizable()
                .scaledToFit()
                .frame(width: width)
                .keyframeAnimator(initialValue: LoginMascotPose(), trigger: replay) { content, pose in
                    content
                        .scaleEffect(reduceMotion ? 1 : pose.scale, anchor: .bottom)
                        .rotationEffect(.degrees(reduceMotion ? -3 : pose.angle), anchor: .bottom)
                        .offset(y: reduceMotion ? 0 : pose.lift)
                } keyframes: { _ in
                    KeyframeTrack(\.lift) {
                        CubicKeyframe(0, duration: 0.12)
                        CubicKeyframe(-width * 0.09, duration: 0.24)
                        SpringKeyframe(0, duration: 0.48, spring: .bouncy)
                    }
                    KeyframeTrack(\.scale) {
                        CubicKeyframe(0.94, duration: 0.12)
                        CubicKeyframe(1.04, duration: 0.24)
                        SpringKeyframe(1, duration: 0.48, spring: .snappy)
                    }
                    KeyframeTrack(\.angle) {
                        CubicKeyframe(4, duration: 0.20)
                        CubicKeyframe(-8, duration: 0.28)
                        SpringKeyframe(-3, duration: 0.36, spring: .smooth)
                    }
                }
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel("MakanApa mascot")
        .accessibilityHint("Tap for a little celebration")
        .onAppear { if !reduceMotion { replay += 1 } }
    }
}
