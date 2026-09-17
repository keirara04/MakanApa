import SwiftUI

struct LoginFieldStyle: ViewModifier {
    let isFocused: Bool
    let accent: Color
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func body(content: Content) -> some View {
        content
            .overlay {
                RoundedRectangle(cornerRadius: 16)
                    .strokeBorder(isFocused ? accent : Color.kicap.opacity(0.12), lineWidth: isFocused ? 1.5 : 1)
            }
            .shadow(color: accent.opacity(isFocused ? 0.08 : 0), radius: 6, y: 2)
            .animation(reduceMotion ? nil : .easeOut(duration: 0.18), value: isFocused)
    }
}
