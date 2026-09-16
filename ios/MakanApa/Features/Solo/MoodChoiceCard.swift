import SwiftUI

struct MoodChoiceCard: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @ScaledMetric(relativeTo: .body) private var cardHeight = 158

    let option: SoloViewModel.MoodOption
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 12) {
                HStack(alignment: .top) {
                    Image(option.illustration)
                        .resizable()
                        .scaledToFit()
                        .frame(width: 56, height: 56)
                        .accessibilityHidden(true)
                    Spacer(minLength: 0)
                    Image(systemName: isSelected ? "checkmark.circle.fill" : "circle")
                        .font(.system(size: 21, weight: .regular))
                        .foregroundStyle(isSelected ? Color.sambalRed : Color.kicap.opacity(0.16))
                        .accessibilityHidden(true)
                }
                Spacer(minLength: 0)
                VStack(alignment: .leading, spacing: 5) {
                    Text(option.label)
                        .font(.system(.title3, design: .rounded, weight: .bold))
                    Text(option.subtext)
                        .font(.footnote)
                        .foregroundStyle(Color.kicap.opacity(0.68))
                        .fixedSize(horizontal: false, vertical: true)
                        .frame(minHeight: 34, alignment: .topLeading)
                }
            }
            .foregroundStyle(Color.kicap)
            .padding(18)
            .frame(maxWidth: .infinity, alignment: .leading)
            .frame(minHeight: cardHeight)
            .background(isSelected ? Color.sambalRed.opacity(0.07) : .white.opacity(0.72),
                        in: RoundedRectangle(cornerRadius: 22))
            .overlay {
                RoundedRectangle(cornerRadius: 22)
                    .strokeBorder(isSelected ? Color.sambalRed : Color.kicap.opacity(0.07),
                                  lineWidth: isSelected ? 1.5 : 1)
            }
            .contentShape(RoundedRectangle(cornerRadius: 22))
        }
        .buttonStyle(.plain)
        .animation(reduceMotion ? nil : .easeInOut(duration: 0.18), value: isSelected)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("\(option.label), \(option.subtext)")
        .accessibilityAddTraits(isSelected ? [.isButton, .isSelected] : .isButton)
    }
}
