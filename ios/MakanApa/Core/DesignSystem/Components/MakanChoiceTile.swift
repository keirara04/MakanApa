import SwiftUI
import UIKit

/// Tappable tile used for mood/budget/distance choices, with a defined selected state.
struct MakanChoiceTile: View {
    let emoji: String
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
                Text(emoji)
                    .font(.system(size: 32))
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
            .frame(height: 96)
        }
        .background(isSelected ? Color.sambalRed : Color.kicap.opacity(0.06))
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .overlay(
            RoundedRectangle(cornerRadius: 18)
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
        .scaleEffect(isSelected ? 1.03 : 1.0)
        .animation(.spring(response: 0.25, dampingFraction: 0.7), value: isSelected)
        .accessibilityLabel(label)
        .accessibilityAddTraits(isSelected ? [.isButton, .isSelected] : .isButton)
    }
}
