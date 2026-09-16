import SwiftUI

/// Compact text wordmark used on every screen except Home, where the full logo lockup appears instead.
struct MakanApaTopBar: View {
    var trailing: String?
    var onBack: (() -> Void)? = nil

    var body: some View {
        HStack(spacing: 12) {
            if let onBack {
                Button(action: onBack) {
                    Image(systemName: "chevron.left")
                        .font(.system(size: 17, weight: .semibold))
                        .foregroundStyle(Color.kicap)
                }
                .accessibilityLabel("Back")
            }

            (Text("Makan").foregroundStyle(Color.kicap) + Text("Apa?").foregroundStyle(Color.sambalRed))
                .font(.makanDisplay(20))

            Spacer()

            if let trailing {
                Text(trailing)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)
            }
        }
        .padding(.horizontal)
        .padding(.top, 8)
    }
}
