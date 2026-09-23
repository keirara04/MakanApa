import SwiftUI

/// A retro marquee band of dish names. Driven by the caller's clock (`time`) rather than its
/// own animation, so one TimelineView can run the whole hero and pause it in one place.
struct LoginTickerRibbon: View {
    let items: [String]
    let fill: Color
    let ink: Color
    /// Negative scrolls left, positive scrolls right.
    let pointsPerSecond: Double
    let time: TimeInterval

    @State private var runWidth: CGFloat = 0

    var body: some View {
        HStack(spacing: 0) {
            TickerRun(items: items, ink: ink)
                .onGeometryChange(for: CGFloat.self) { $0.size.width } action: { runWidth = $0 }
            TickerRun(items: items, ink: ink)
            TickerRun(items: items, ink: ink)
        }
        .fixedSize()
        .offset(x: offset)
        .frame(maxWidth: .infinity, alignment: .leading)
        .frame(height: 34)
        .background(fill)
        .clipped()
        .accessibilityHidden(true)
    }

    /// Wraps every `runWidth` points; three copies keep the band covered at either extreme.
    private var offset: CGFloat {
        guard runWidth > 0 else { return 0 }
        let shift = (time * abs(pointsPerSecond)).truncatingRemainder(dividingBy: runWidth)
        return pointsPerSecond < 0 ? -shift : shift - runWidth
    }
}

/// One pass of the dish list. Its own view so the text isn't re-laid-out every frame — only
/// the parent's offset changes as the clock ticks.
private struct TickerRun: View {
    let items: [String]
    let ink: Color

    var body: some View {
        HStack(spacing: 14) {
            ForEach(items, id: \.self) { item in
                Text(item.uppercased())
                Image(systemName: "sparkle")
                    .font(.system(size: 10, weight: .black))
            }
        }
        .padding(.trailing, 14)
        .font(.system(size: 13, weight: .heavy, design: .rounded))
        .tracking(1.4)
        .foregroundStyle(ink)
        .lineLimit(1)
    }
}
