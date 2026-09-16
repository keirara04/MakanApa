import SwiftUI

struct PreferenceActionFooter: View {
    let title: String
    let note: String
    var isEnabled = true
    let action: () -> Void

    var body: some View {
        VStack(spacing: 12) {
            Button(action: action) {
                HStack {
                    Text(title)
                    Spacer()
                    Image(systemName: "arrow.right")
                        .accessibilityHidden(true)
                }
                .font(.headline)
                .foregroundStyle(isEnabled ? .white : Color.kicap.opacity(0.45))
                .padding(.horizontal, 22)
                .padding(.vertical, 16)
                .frame(minHeight: 56)
                .background(isEnabled ? Color.sambalRed : Color.kicap.opacity(0.08),
                            in: RoundedRectangle(cornerRadius: 18))
            }
            .buttonStyle(.plain)
            .disabled(!isEnabled)
            .accessibilityHint(note)

            Text(note)
                .font(.footnote)
                .foregroundStyle(Color.kicap.opacity(0.65))
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: 492)
        .padding(.horizontal, 24)
        .padding(.top, 16)
        .padding(.bottom, 12)
        .frame(maxWidth: .infinity)
        .background(Color.nasiCream)
    }
}
