import SwiftUI

/// Step 1. Order matters: the fastest paths come first — type a craving, or "Anything lah" —
/// then the picture list. Previously the text field and "Anything lah" sat below all ten cards,
/// several scrolls down.
struct MoodSelectionView: View {
    let cravingSelection: SoloViewModel.CravingSelection?
    @Binding var customText: String
    let onSelectTag: (String) -> Void
    let onAnything: () -> Void
    var onSubmitCustom: () -> Void = {}

    @FocusState private var fieldFocused: Bool

    private var choseAnything: Bool { cravingSelection == .anything }
    private var isCustomActive: Bool {
        if case .custom = cravingSelection { return true }
        return false
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            PreferenceStepHeading(
                title: Copy.soloMoodPrompt,
                subtitle: "Ikut selera. What are you craving?"
            )

            HStack(spacing: 10) {
                Image(systemName: "magnifyingglass")
                    .foregroundStyle(Color.kicap.opacity(0.45))
                    .accessibilityHidden(true)
                TextField(Copy.moodCustomCravingPlaceholder, text: $customText)
                    .font(.system(.body, design: .rounded))
                    .focused($fieldFocused)
                    .submitLabel(.next)
                    .onSubmit(onSubmitCustom)
                if !customText.isEmpty {
                    Button {
                        customText = ""
                    } label: {
                        Image(systemName: "xmark.circle.fill").foregroundStyle(Color.kicap.opacity(0.35))
                    }
                    .accessibilityLabel("Clear craving")
                }
            }
            .padding(.horizontal, 16)
            .frame(minHeight: 52)
            .background(.white.opacity(0.72), in: RoundedRectangle(cornerRadius: 18))
            .overlay {
                RoundedRectangle(cornerRadius: 18)
                    .strokeBorder(isCustomActive || fieldFocused ? Color.sambalRed : Color.kicap.opacity(0.12),
                                  lineWidth: isCustomActive || fieldFocused ? 1.5 : 1)
            }

            PreferenceChoiceRow(
                symbol: "dice",
                title: "Anything lah",
                subtitle: "Good food can surprise me.",
                isSelected: choseAnything,
                isSecondary: true,
                action: onAnything
            )

            VStack(alignment: .leading, spacing: 10) {
                Text("Or pick one")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Color.kicap.opacity(0.6))
                LazyVStack(spacing: 8) {
                    ForEach(SoloViewModel.moodOptions, id: \.tag) { option in
                        MoodChoiceCard(
                            option: option,
                            isSelected: cravingSelection == .tag(option.tag)
                        ) {
                            fieldFocused = false
                            onSelectTag(option.tag)
                        }
                    }
                }
            }
        }
        .padding(.horizontal, 24)
    }
}
