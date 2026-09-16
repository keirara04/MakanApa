import SwiftUI

/// "Nasi" the mascot, represented as an emoji placeholder until a real illustration exists.
struct MascotLine: View {
    let caption: String

    var body: some View {
        VStack(spacing: 4) {
            Text("🍚")
                .font(.system(size: 28))
            Text(caption)
                .font(.makanBody(14))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }
    }
}
