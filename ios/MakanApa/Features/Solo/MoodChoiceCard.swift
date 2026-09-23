import SwiftUI

/// Compact craving row: photo beside the text, full width, so title and subtext always read in
/// full (a 2-column grid truncated them mid-word) — same shape family as the budget/distance
/// PreferenceChoiceRows, just denser. Ten fit in about 1.5 screen-heights instead of five.
struct MoodChoiceCard: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @ScaledMetric(relativeTo: .body) private var cardHeight = 64
    @ScaledMetric(relativeTo: .body) private var imageSize = 44

    let option: SoloViewModel.MoodOption
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                Image(option.illustration)
                    .resizable()
                    .scaledToFill()
                    .frame(width: imageSize, height: imageSize)
                    .clipShape(RoundedRectangle(cornerRadius: 12))
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text(option.label)
                        .font(.system(.headline, design: .rounded, weight: .bold))
                    Text(option.subtext)
                        .font(.footnote)
                        .foregroundStyle(Color.kicap.opacity(0.65))
                        .fixedSize(horizontal: false, vertical: true)
                }
                Spacer(minLength: 0)
                Image(systemName: isSelected ? "checkmark.circle.fill" : "circle")
                    .font(.system(size: 21))
                    .foregroundStyle(isSelected ? Color.sambalRed : Color.kicap.opacity(0.16))
                    .accessibilityHidden(true)
            }
            .foregroundStyle(Color.kicap)
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .frame(maxWidth: .infinity, minHeight: cardHeight, alignment: .leading)
            .background(isSelected ? Color.sambalRed.opacity(0.07) : .white.opacity(0.72),
                        in: RoundedRectangle(cornerRadius: 18))
            .overlay {
                RoundedRectangle(cornerRadius: 18)
                    .strokeBorder(isSelected ? Color.sambalRed : Color.kicap.opacity(0.07),
                                  lineWidth: isSelected ? 1.5 : 1)
            }
            .contentShape(RoundedRectangle(cornerRadius: 18))
        }
        .buttonStyle(PressCompressStyle())
        .animation(reduceMotion ? nil : .easeInOut(duration: 0.18), value: isSelected)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("\(option.label), \(option.subtext)")
        .accessibilityAddTraits(isSelected ? [.isButton, .isSelected] : .isButton)
    }
}
