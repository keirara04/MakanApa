import SwiftUI

struct PreferenceChoiceRow: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var illustration: String? = nil
    var symbol: String? = nil
    let title: String
    let subtitle: String
    let isSelected: Bool
    var isSecondary = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 16) {
                Group {
                    if let illustration {
                        Image(illustration)
                            .resizable()
                            .scaledToFit()
                    } else if let symbol {
                        Image(systemName: symbol)
                            .font(.title2)
                            .foregroundStyle(Color.pandan)
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                            .background(Color.pandan.opacity(0.08), in: RoundedRectangle(cornerRadius: 12))
                    }
                }
                .frame(width: isSecondary ? 44 : 56, height: isSecondary ? 44 : 56)
                .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 5) {
                    Text(title)
                        .font(.system(isSecondary ? .headline : .title3, design: .rounded, weight: .bold))
                    Text(subtitle)
                        .font(.subheadline)
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
            .padding(isSecondary ? 16 : 18)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(isSelected ? Color.sambalRed.opacity(0.07) : .white.opacity(isSecondary ? 0.4 : 0.72),
                        in: RoundedRectangle(cornerRadius: 22))
            .overlay {
                RoundedRectangle(cornerRadius: 22)
                    .strokeBorder(isSelected ? Color.sambalRed : Color.kicap.opacity(isSecondary ? 0.12 : 0.07),
                                  lineWidth: isSelected ? 1.5 : 1)
            }
            .contentShape(RoundedRectangle(cornerRadius: 22))
        }
        .buttonStyle(.plain)
        .animation(reduceMotion ? nil : .easeInOut(duration: 0.18), value: isSelected)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("\(title), \(subtitle)")
        .accessibilityAddTraits(isSelected ? [.isButton, .isSelected] : .isButton)
    }
}
