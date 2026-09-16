import SwiftUI
import UIKit

/// Tappable tile used for mood/budget/distance choices, with a defined selected state.
/// Prefers bespoke illustration art; falls back to emoji/text for any tile without one.
struct MakanChoiceTile: View {
    var illustration: String? = nil
    var emoji: String? = nil
    let label: String
    var subtext: String? = nil
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            action()
        } label: {
            VStack(spacing: subtext == nil ? 8 : 4) {
                if let illustration {
                    Image(illustration)
                        .resizable()
                        .scaledToFit()
                        .frame(width: 46, height: 46)
                        .accessibilityHidden(true)
                } else if let emoji {
                    Text(emoji)
                        .font(.system(size: 32))
                }
                Text(label)
                    .font(.makanBody(15))
                    .foregroundStyle(isSelected ? .white : Color.kicap)
                if let subtext {
                    Text(subtext)
                        .font(.makanBody(11))
                        .foregroundStyle(isSelected ? .white.opacity(0.85) : Color.kicap.opacity(0.6))
                }
            }
            .frame(maxWidth: .infinity)
            .frame(height: 104)
        }
        .background(isSelected ? Color.sambalRed : Color.kicap.opacity(0.06))
        .clipShape(RoundedRectangle(cornerRadius: 22))
        .overlay(
            RoundedRectangle(cornerRadius: 22)
                .strokeBorder(isSelected ? Color.sambalRed : .clear, lineWidth: 2)
        )
        .overlay(alignment: .topTrailing) {
            if isSelected {
                Image(systemName: "checkmark.circle.fill")
                    .font(.system(size: 16))
                    .foregroundStyle(.white)
                    .padding(6)
            }
        }
        .shadow(
            color: Color.kicap.opacity(isSelected ? 0.10 : 0.025),
            radius: isSelected ? 8 : 3,
            y: isSelected ? 4 : 1
        )
        .scaleEffect(isSelected ? 1.03 : 1.0)
        .animation(.spring(response: 0.25, dampingFraction: 0.7), value: isSelected)
        .accessibilityLabel(accessibilityText)
        .accessibilityAddTraits(isSelected ? [.isButton, .isSelected] : .isButton)
    }

    private var accessibilityText: String {
        var parts = [label]
        if let subtext { parts.append(subtext) }
        if isSelected { parts.append("selected") }
        return parts.joined(separator: ", ")
    }
}
