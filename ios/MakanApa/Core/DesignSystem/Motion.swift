import SwiftUI

/// Standardized animation curves so motion reads as one system instead of ad-hoc spring values
/// sprinkled per view. Pick by what the motion is *for*, not by feel: state toggles get `quick`,
/// anything sliding/appearing gets `standard`, anything that should feel a little delighted
/// (save, marker selection) gets `playful`.
enum Motion {
    static let quick = Animation.easeOut(duration: 0.18)
    static let standard = Animation.spring(response: 0.32, dampingFraction: 0.82)
    static let playful = Animation.spring(response: 0.42, dampingFraction: 0.68)
}

/// Compresses to 0.96 on press — the standard "this button is alive" feedback for primary CTAs
/// (Pick one lah, etc). Distinct from `PressableCardStyle` in HomeView (0.97/quicker) since Home's
/// cards are larger tap targets; keep both, don't force a false shared identity between them.
struct PressCompressStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.96 : 1.0)
            .animation(Motion.playful, value: configuration.isPressed)
    }
}
