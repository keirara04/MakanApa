import SwiftUI
import UIKit

/// Tappable tile used for mood/budget/distance choices, with a defined selected state.
struct MakanChoiceTile: View {
    let emoji: String
    let label: String
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            action()
        } label: {
            VStack(spacing: 8) {
                Text(emoji)
                    .font(.system(size: 32))
                Text(label)
                    .font(.makanBody(15))
                    .foregroundStyle(isSelected ? .white : Color.kicap)
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 20)
        }
        .background(isSelected ? Color.sambalRed : Color.kicap.opacity(0.06))
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .overlay(
            RoundedRectangle(cornerRadius: 18)
                .strokeBorder(isSelected ? Color.sambalRed : .clear, lineWidth: 2)
        )
        .scaleEffect(isSelected ? 1.03 : 1.0)
        .animation(.spring(response: 0.25, dampingFraction: 0.7), value: isSelected)
    }
}
