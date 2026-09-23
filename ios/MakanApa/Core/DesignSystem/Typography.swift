import SwiftUI
import UIKit

extension Font {
    /// Big, decisive, opinionated headlines — "NASI KANDAR.", "MAKANAPA?", "Settled."
    /// Follows the user's text size, capped so a 40pt headline can't blow out the layout.
    static func makanDisplay(_ size: CGFloat) -> Font {
        .system(size: scaled(size, relativeTo: .title1, maxScale: 1.35), weight: .heavy, design: .rounded)
    }

    /// Supporting body copy alongside display text. Follows the user's text size.
    static func makanBody(_ size: CGFloat = 16) -> Font {
        .system(size: scaled(size, relativeTo: .body, maxScale: 1.8), weight: .medium, design: .rounded)
    }

    /// The design sizes are the "Large" (default) text size; UIFontMetrics scales them the same
    /// way the matching system text style scales. Read when a view's body is evaluated, so a
    /// text-size change mid-session applies as each screen re-renders.
    private static func scaled(_ size: CGFloat, relativeTo style: UIFont.TextStyle, maxScale: CGFloat) -> CGFloat {
        min(UIFontMetrics(forTextStyle: style).scaledValue(for: size), size * maxScale)
    }
}
