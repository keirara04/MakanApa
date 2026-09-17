import SwiftUI

/// A short welcome performance, replayable by touch. No perpetual animation work.
struct LoginMascotView: View {
    let size: CGFloat
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var replay = 0

    var body: some View {
        Button {
            replay += 1
        } label: {
            ZStack {
                Circle()
                    .fill(Color(red: 0.97, green: 0.91, blue: 0.81))
                    .frame(width: size * 0.88, height: size * 0.88)
                Circle()
                    .strokeBorder(.white.opacity(0.7), lineWidth: 1.5)
                    .frame(width: size * 0.75, height: size * 0.75)

                Ellipse()
                    .fill(Color.kicap.opacity(0.10))
                    .frame(width: size * 0.35, height: 7)
                    .blur(radius: 3)
                    .offset(y: size * 0.40)

                Image("MascotCelebrate")
                    .resizable()
                    .scaledToFit()
                    .frame(width: size * 1.2, height: size * 1.2)
                    .keyframeAnimator(initialValue: LoginMascotPose(), trigger: replay) { content, pose in
                        content
                            .scaleEffect(reduceMotion ? 1 : pose.scale, anchor: .bottom)
                            .rotationEffect(.degrees(reduceMotion ? -5 : pose.angle), anchor: .bottom)
                            .offset(y: reduceMotion ? -7 : pose.lift - 7)
                    } keyframes: { _ in
                        KeyframeTrack(\.lift) {
                            CubicKeyframe(0, duration: 0.12)
                            CubicKeyframe(-18, duration: 0.24)
                            SpringKeyframe(0, duration: 0.48, spring: .bouncy)
                        }
                        KeyframeTrack(\.scale) {
                            CubicKeyframe(0.94, duration: 0.12)
                            CubicKeyframe(1.04, duration: 0.24)
                            SpringKeyframe(1, duration: 0.48, spring: .snappy)
                        }
                        KeyframeTrack(\.angle) {
                            CubicKeyframe(7, duration: 0.20)
                            CubicKeyframe(-11, duration: 0.28)
                            SpringKeyframe(-5, duration: 0.36, spring: .smooth)
                        }
                    }
            }
            .frame(width: size, height: size)
            .contentShape(Circle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel("MakanApa mascot")
        .accessibilityHint("Tap for a little celebration")
        .onAppear { if !reduceMotion { replay += 1 } }
    }
}
