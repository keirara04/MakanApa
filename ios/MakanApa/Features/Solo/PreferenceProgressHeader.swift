import SwiftUI

struct PreferenceProgressHeader: View {
    let step: Int
    let onBack: () -> Void

    private let steps = ["Mood", "Budget", "Distance"]

    var body: some View {
        VStack(spacing: 22) {
            HStack {
                Button("Back", systemImage: "chevron.left", action: onBack)
                    .labelStyle(.iconOnly)
                    .font(.body.weight(.semibold))
                    .foregroundStyle(Color.kicap)
                    .frame(width: 44, height: 44)
                    .background(.white.opacity(0.6), in: Circle())

                Spacer()
                (Text("Makan").foregroundStyle(Color.kicap)
                 + Text("Apa?").foregroundStyle(Color.sambalRed))
                    .font(.system(.title3, design: .rounded, weight: .heavy))
                Spacer()
                Text("\(step + 1) / 3")
                    .font(.subheadline.weight(.medium))
                    .monospacedDigit()
                    .foregroundStyle(Color.kicap.opacity(0.6))
                    .frame(minWidth: 44)
            }

            HStack(spacing: 8) {
                ForEach(steps.indices, id: \.self) { index in
                    VStack(alignment: .leading, spacing: 8) {
                        Capsule()
                            .fill(index <= step ? Color.sambalRed : Color.kicap.opacity(0.1))
                            .frame(height: 3)
                        Text(steps[index])
                            .font(.caption.weight(index == step ? .semibold : .regular))
                            .foregroundStyle(index == step ? Color.kicap : Color.kicap.opacity(0.6))
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                }
            }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Step \(step + 1) of 3, \(steps[step])")
        }
        .frame(maxWidth: 492)
        .padding(.horizontal, 24)
        .padding(.top, 8)
        .frame(maxWidth: .infinity)
    }
}
