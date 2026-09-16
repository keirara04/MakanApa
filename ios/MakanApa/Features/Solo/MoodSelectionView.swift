import SwiftUI

struct MoodSelectionView: View {
    @Environment(\.dynamicTypeSize) private var dynamicTypeSize
    @ScaledMetric(relativeTo: .largeTitle) private var titleSize = 36

    let selectedTags: Set<String>
    let choseAnything: Bool
    let onSelect: (String) -> Void
    let onAnything: () -> Void

    private var columns: [GridItem] {
        Array(repeating: GridItem(.flexible(), spacing: 12),
              count: dynamicTypeSize.isAccessibilitySize ? 1 : 2)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            VStack(alignment: .leading, spacing: 10) {
                Text(Copy.soloMoodPrompt)
                    .font(.system(size: titleSize, weight: .bold, design: .rounded))
                    .tracking(-1.2)
                    .foregroundStyle(Color.kicap)
                    .accessibilityAddTraits(.isHeader)
                Text("Ikut selera. What are you craving?")
                    .font(.subheadline)
                    .foregroundStyle(Color.kicap.opacity(0.65))
            }
            .padding(.top, 4)

            LazyVGrid(columns: columns, spacing: 12) {
                ForEach(SoloViewModel.moodOptions, id: \.tag) { option in
                    MoodChoiceCard(
                        option: option,
                        isSelected: selectedTags.contains(option.tag)
                    ) {
                        onSelect(option.tag)
                    }
                }
            }

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
