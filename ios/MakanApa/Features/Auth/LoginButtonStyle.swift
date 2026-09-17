import SwiftUI

struct LoginButtonStyle: ButtonStyle {
    let accent: Color
    var isLoading = false
    @Environment(\.isEnabled) private var isEnabled
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .foregroundStyle(isEnabled || isLoading ? Color.white : Color(red: 0.43, green: 0.37, blue: 0.34))
            .background(isEnabled || isLoading ? accent : Color(red: 0.89, green: 0.86, blue: 0.82),
                        in: RoundedRectangle(cornerRadius: 16))
            .shadow(color: accent.opacity(isEnabled || isLoading ? 0.15 : 0), radius: 10, y: 5)
            .brightness(configuration.isPressed ? -0.04 : 0)
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.975 : 1)
            .animation(reduceMotion ? nil : .spring(duration: 0.24, bounce: 0.15), value: configuration.isPressed)
            .animation(.easeOut(duration: 0.2), value: isEnabled)
    }
}
