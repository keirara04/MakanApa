import SwiftUI

/// Compact text wordmark used on every screen except Home, where the full logo lockup appears instead.
struct MakanApaTopBar: View {
    var trailing: String?

    var body: some View {
        HStack {
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
