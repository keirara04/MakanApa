import SwiftUI

/// Lifts each glyph out of a soft blur, left to right, as `progress` runs 0 → 1. Animate
/// `progress` with `withAnimation` — the renderer is `Animatable`, so SwiftUI interpolates it.
struct GlyphRevealRenderer: TextRenderer, Animatable {
    var progress: Double

    var animatableData: Double {
        get { progress }
        set { progress = newValue }
    }

    /// Share of the timeline spent staggering starts; the rest is each glyph's own rise.
    private let spread = 0.55

    func draw(layout: Text.Layout, in context: inout GraphicsContext) {
        guard progress < 1 else {
            for line in layout { context.draw(line) }
            return
        }

        let glyphs = layout.flatMap { $0 }.flatMap { $0 }
        guard !glyphs.isEmpty else { return }

        for (index, glyph) in glyphs.enumerated() {
            let start = Double(index) / Double(glyphs.count) * spread
            let local = min(max((progress - start) / (1 - spread), 0), 1)
            let eased = 1 - pow(1 - local, 3)
            guard eased > 0 else { continue }

            var copy = context
            copy.opacity = eased
            copy.translateBy(x: 0, y: (1 - eased) * glyph.typographicBounds.rect.height * 0.45)
            copy.addFilter(.blur(radius: (1 - eased) * 6))
            copy.draw(glyph, options: .disablesSubpixelQuantization)
        }
    }
}
