import SwiftUI

/// Shared tactile feedback for community actions, with a stationary Reduce Motion variant.
struct CommunityPressStyle: ButtonStyle {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.97 : 1)
            .opacity(configuration.isPressed ? 0.82 : 1)
            .animation(reduceMotion ? .easeOut(duration: 0.12) : Motion.standard, value: configuration.isPressed)
    }
}
