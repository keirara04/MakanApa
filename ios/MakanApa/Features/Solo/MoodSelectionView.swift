import SwiftUI

struct MoodSelectionView: View {
    @Environment(\.dynamicTypeSize) private var dynamicTypeSize

    let cravingSelection: SoloViewModel.CravingSelection?
    @Binding var customText: String
    let onSelectTag: (String) -> Void
    let onAnything: () -> Void

    private var choseAnything: Bool { cravingSelection == .anything }
    private var isCustomActive: Bool {
        if case .custom = cravingSelection { return true }
        return false
    }

    private var columns: [GridItem] {
        Array(repeating: GridItem(.flexible(), spacing: 12),
              count: dynamicTypeSize.isAccessibilitySize ? 1 : 2)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            PreferenceStepHeading(
                title: Copy.soloMoodPrompt,
                subtitle: "Ikut selera. What are you craving?"
            )

            LazyVGrid(columns: columns, spacing: 12) {
                ForEach(SoloViewModel.moodOptions, id: \.tag) { option in
                    MoodChoiceCard(
                        option: option,
                        isSelected: cravingSelection == .tag(option.tag)
                    ) {
                        onSelectTag(option.tag)
                    }
                }
            }

            TextField(Copy.moodCustomCravingPlaceholder, text: $customText)
                .textFieldStyle(.plain)
                .font(.system(.body, design: .rounded))
                .padding(16)
                .background(.white.opacity(0.72), in: RoundedRectangle(cornerRadius: 20))
                .overlay {
                    RoundedRectangle(cornerRadius: 20)
                        .strokeBorder(isCustomActive ? Color.sambalRed : Color.kicap.opacity(0.12),
                                      lineWidth: isCustomActive ? 1.5 : 1)
                }
                .submitLabel(.done)

            Button(action: onAnything) {
                HStack(spacing: 14) {
                    Image(systemName: "dice")
                        .font(.title2.weight(.regular))
                        .foregroundStyle(Color.pandan)
                        .frame(width: 44, height: 44)
                        .background(Color.pandan.opacity(0.08), in: RoundedRectangle(cornerRadius: 12))
                        .accessibilityHidden(true)
                    VStack(alignment: .leading, spacing: 4) {
                        Text("Anything lah")
                            .font(.system(.headline, design: .rounded))
                        Text("Good food can surprise me.")
                            .font(.footnote)
                            .foregroundStyle(Color.kicap.opacity(0.65))
                    }
                    Spacer(minLength: 0)
                    Image(systemName: choseAnything ? "checkmark.circle.fill" : "circle")
                        .font(.title3)
                        .foregroundStyle(choseAnything ? Color.sambalRed : Color.kicap.opacity(0.2))
                        .accessibilityHidden(true)
                }
                .foregroundStyle(Color.kicap)
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(.white.opacity(0.4), in: RoundedRectangle(cornerRadius: 20))
                .overlay {
                    RoundedRectangle(cornerRadius: 20)
                        .strokeBorder(choseAnything ? Color.sambalRed : Color.kicap.opacity(0.12),
                                      lineWidth: choseAnything ? 1.5 : 1)
                }
                .contentShape(RoundedRectangle(cornerRadius: 20))
            }
            .buttonStyle(.plain)
            .accessibilityAddTraits(choseAnything ? .isSelected : [])
        }
        .padding(.horizontal, 24)
    }
}
