import SwiftUI
import UIKit

struct PreferenceView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var viewModel

    @State private var step: Int = 0
    @State private var isThinking = false

    private let totalSteps = 3

    var body: some View {
        VStack(spacing: 32) {
            MakanApaTopBar(trailing: "\(step + 1)/\(totalSteps)")

            Spacer()

            if isThinking {
                thinkingView
            } else {
                switch step {
                case 0: moodStep
                case 1: budgetStep
                default: distanceStep
                }
            }

            Spacer()
        }
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
    }

    // MARK: - Step 0: Mood

    private var moodStep: some View {
        VStack(spacing: 24) {
            Text(Copy.soloMoodPrompt)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)

            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 16) {
                ForEach(SoloViewModel.moodOptions, id: \.tag) { option in
                    MakanChoiceTile(
                        emoji: option.emoji,
                        label: option.label,
                        isSelected: viewModel.selectedMoodTags.contains(option.tag)
                    ) {
                        selectMood(option.tag)
                    }
                }
            }
            .padding(.horizontal)

            Button {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                viewModel.selectedMoodTags = []
                viewModel.selectedCuisines = []
                advance()
            } label: {
                Text(Copy.soloAnythingLah)
                    .font(.makanBody(16))
                    .foregroundStyle(.secondary)
            }
        }
    }

    private func selectMood(_ tag: String) {
        viewModel.selectedMoodTags = [tag]
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.2) {
            advance()
        }
    }

    // MARK: - Step 1: Budget

    private var budgetStep: some View {
        VStack(spacing: 24) {
            Text(Copy.soloBudgetPrompt)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)

            HStack(spacing: 12) {
                ForEach(SoloViewModel.budgetOptions, id: \.tier) { option in
                    MakanChoiceTile(
                        emoji: option.symbol,
                        label: option.label,
                        isSelected: viewModel.budgetMax == option.tier
                    ) {
                        selectBudget(option.tier)
                    }
                }
            }
            .padding(.horizontal)
        }
    }

    private func selectBudget(_ tier: Int) {
        viewModel.budgetMax = tier
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.2) {
            advance()
        }
    }

    // MARK: - Step 2: Distance

    private var distanceStep: some View {
        VStack(spacing: 24) {
            Text(Copy.soloDistancePrompt)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)

            HStack(spacing: 12) {
                ForEach(SoloViewModel.distanceOptions, id: \.km) { option in
                    MakanChoiceTile(
                        emoji: option.emoji,
                        label: option.label,
                        isSelected: viewModel.maxDistanceKm == option.km
                    ) {
                        UIImpactFeedbackGenerator(style: .light).impactOccurred()
                        viewModel.maxDistanceKm = option.km
                    }
                }
            }
            .padding(.horizontal)

            MakanPrimaryButton(title: Copy.soloCTA) {
                startThinking()
            }
            .padding(.horizontal)
        }
    }

    // MARK: - Thinking transition

    private var thinkingView: some View {
        MascotLine(caption: Copy.thinking)
    }

    private func startThinking() {
        isThinking = true
        viewModel.decide()
        Task {
            try? await Task.sleep(for: .milliseconds(500))
            router.push(.soloResult)
            isThinking = false
        }
    }

    private func advance() {
        if step < totalSteps - 1 {
            step += 1
        }
    }
}
