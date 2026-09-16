import SwiftUI

extension Font {
    /// Big, decisive, opinionated headlines — "NASI KANDAR.", "MAKANAPA?", "Settled."
    static func makanDisplay(_ size: CGFloat) -> Font {
        .system(size: size, weight: .heavy, design: .rounded)
    }

    /// Supporting body copy alongside display text.
    static func makanBody(_ size: CGFloat = 16) -> Font {
        .system(size: size, weight: .medium, design: .rounded)
    }
}
