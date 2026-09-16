import SwiftUI

/// Progressive 3-line checklist — reads as "deciding," not a network spinner.
/// One-shot ladder (0/220/440ms), not a repeating loop: if the real request outlasts
/// the ladder, all three lines simply stay lit rather than cycling.
struct ThinkingChecklist: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var activeCount = 0

    var lines: [String] = [Copy.thinkingStep1, Copy.thinkingStep2, Copy.thinkingStep3]

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            ForEach(lines.indices, id: \.self) { index in
                HStack(spacing: 8) {
                    Circle()
                        .fill(index < activeCount ? Color.sambalRed : Color.kicap.opacity(0.15))
                        .frame(width: 8, height: 8)
                    Text(lines[index])
                        .font(.makanBody(13))
                        .foregroundStyle(index < activeCount ? Color.kicap : .secondary)
                }
            }
        }
        .animation(.easeOut(duration: 0.2), value: activeCount)
        .task {
            if reduceMotion {
                activeCount = lines.count
                return
            }
            for _ in lines {
                activeCount += 1
                try? await Task.sleep(for: .milliseconds(220))
            }
        }
    }
}
