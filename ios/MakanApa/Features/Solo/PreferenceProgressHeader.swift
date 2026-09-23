import SwiftUI

/// Back · wordmark, then a 3-segment progress bar whose labels turn into your answers once a
/// step is done ("Nasi Kandar", "~RM20") — tap one to jump back and change it. Replaces the old
/// "1 / 3" counter, which just repeated what the bar already said.
struct PreferenceProgressHeader: View {
    let step: Int
    /// Your answer per step, shown in place of the step name once that step is behind you.
    var answers: [String?] = [nil, nil, nil]
    let onBack: () -> Void
    var onJump: (Int) -> Void = { _ in }

    private let steps = ["Mood", "Budget", "Distance"]

    var body: some View {
        VStack(spacing: 22) {
            HStack {
                CircleBackButton(action: onBack)
                Spacer()
                (Text("Makan").foregroundStyle(Color.kicap)
                 + Text("Apa?").foregroundStyle(Color.sambalRed))
                    .font(.system(.title3, design: .rounded, weight: .heavy))
                Spacer()
                Color.clear.frame(width: 44, height: 44)
            }

            HStack(alignment: .top, spacing: 8) {
                ForEach(steps.indices, id: \.self) { index in
                    let done = index < step
                    Button {
                        onJump(index)
                    } label: {
                        VStack(alignment: .leading, spacing: 8) {
                            Capsule()
                                .fill(index <= step ? Color.sambalRed : Color.kicap.opacity(0.1))
                                .frame(height: 3)
                            HStack(spacing: 3) {
                                Text(done ? (answers[index] ?? steps[index]) : steps[index])
                                    .lineLimit(1)
                                if done {
                                    Image(systemName: "pencil")
                                        .font(.caption2)
                                        .accessibilityHidden(true)
                                }
                            }
                            .font(.caption.weight(index == step ? .semibold : .regular))
                            .foregroundStyle(index == step ? Color.kicap : (done ? Color.sambalRed : Color.kicap.opacity(0.6)))
                        }
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .disabled(!done)
                    .accessibilityLabel(done ? "\(steps[index]): \(answers[index] ?? ""). Change" : steps[index])
                    .accessibilityAddTraits(index == step ? .isSelected : [])
                }
            }
            .animation(.easeOut(duration: 0.2), value: step)
        }
        .frame(maxWidth: 492)
        .padding(.horizontal, 24)
        .padding(.top, 8)
        .frame(maxWidth: .infinity)
    }
}
