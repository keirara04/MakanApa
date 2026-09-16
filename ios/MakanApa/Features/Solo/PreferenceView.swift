import SwiftUI
import UIKit

struct PreferenceView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var viewModel
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var step: Int = 0
    @State private var isThinking = false
    @State private var goingForward = true

    private let totalSteps = 3

    var body: some View {
        VStack(spacing: 0) {
            if isThinking {
                MakanApaTopBar(trailing: nil)
            } else {
                PreferenceProgressHeader(step: step) {
                    if step == 0 { router.pop() } else { goBack() }
                }
            }

            Group {
                if isThinking {
                    thinkingView
                } else {
                    ScrollView {
                        stepContent
                            .frame(maxWidth: 540)
                            .frame(maxWidth: .infinity)
                            .padding(.bottom, 24)
                    }
                    .scrollIndicators(.hidden)
                    .id(step)
                    .transition(stepTransition)
                }
            }
            .padding(.top, 24)
            .animation(reduceMotion ? .easeInOut(duration: 0.2) : .spring(response: 0.4, dampingFraction: 0.85), value: step)
            .animation(.easeInOut(duration: 0.2), value: isThinking)

        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if step == 0 && !isThinking {
                moodContinueButton
            }
        }
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
    }

    @ViewBuilder
    private var stepContent: some View {
        switch step {
        case 0: moodStep
        case 1: budgetStep
        default: distanceStep
        }
    }

    private var stepTransition: AnyTransition {
        guard !reduceMotion else { return .opacity }
        return .asymmetric(
            insertion: .move(edge: goingForward ? .trailing : .leading).combined(with: .opacity),
            removal: .move(edge: goingForward ? .leading : .trailing).combined(with: .opacity)
        )
    }

    // MARK: - Step 0: Mood

    @State private var choseAnything = false

    private var canContinueMood: Bool {
        choseAnything || !viewModel.selectedMoodTags.isEmpty
    }

    private var moodStep: some View {
        MoodSelectionView(
            selectedTags: viewModel.selectedMoodTags,
            choseAnything: choseAnything,
            onSelect: { tag in
                choseAnything = false
                viewModel.selectedMoodTags = [tag]
            },
            onAnything: {
                choseAnything = true
                viewModel.selectedMoodTags = []
            }
        )
    }

    private var moodContinueButton: some View {
        VStack(spacing: 12) {
            Button(action: advance) {
                HStack {
                    Text("Continue")
                    Spacer()
                    Image(systemName: "arrow.right")
                        .accessibilityHidden(true)
                }
                .font(.headline)
                .foregroundStyle(canContinueMood ? .white : Color.kicap.opacity(0.45))
                .padding(.horizontal, 22)
                .frame(minHeight: 56)
                .background(canContinueMood ? Color.sambalRed : Color.kicap.opacity(0.08),
                            in: RoundedRectangle(cornerRadius: 18))
            }
            .buttonStyle(.plain)
            .disabled(!canContinueMood)
            .accessibilityHint("Next, choose your budget")

            Text(canContinueMood ? "Next up, your budget" : "Pick a mood, or leave it to us")
                .font(.footnote)
                .foregroundStyle(Color.kicap.opacity(0.65))
        }
        .sensoryFeedback(.selection, trigger: viewModel.selectedMoodTags)
        .sensoryFeedback(.selection, trigger: choseAnything)
        .frame(maxWidth: 492)
        .padding(.horizontal, 24)
        .padding(.top, 16)
        .padding(.bottom, 12)
        .frame(maxWidth: .infinity)
        .background(Color.nasiCream)
    }

    // MARK: - Step 1: Budget

    private var budgetStep: some View {
        VStack(spacing: 24) {
            VStack(spacing: 4) {
                Text(Copy.soloBudgetPrompt)
                    .font(.makanDisplay(24))
                    .foregroundStyle(Color.kicap)
                Text(Copy.soloBudgetSubtext)
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
            }

            HStack(spacing: 12) {
                ForEach(SoloViewModel.budgetOptions, id: \.tier) { option in
                    MakanChoiceTile(
                        illustration: option.illustration,
                        label: option.amount,
                        subtext: option.label,
                        isSelected: viewModel.budgetMax == option.tier
                    ) {
                        selectBudget(option.tier)
                    }
                }
            }
            .padding(.horizontal)

            Button {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                selectBudget(nil)
            } label: {
                VStack(spacing: 2) {
                    Text(Copy.soloAnythingLah)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)
                    Text(Copy.budgetAnythingSubtext)
                        .font(.makanBody(11))
                        .foregroundStyle(Color.kicap.opacity(0.6))
                }
                .padding(.horizontal, 18)
                .padding(.vertical, 10)
                .overlay(
                    Capsule().strokeBorder(Color.kicap.opacity(0.4), style: StrokeStyle(lineWidth: 1.5, dash: [4, 3]))
                )
            }
        }
    }

    private func selectBudget(_ tier: Int?) {
        viewModel.budgetMax = tier
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.2) {
            advance()
        }
    }

    // MARK: - Step 2: Distance

    private var distanceStep: some View {
        VStack(spacing: 24) {
            VStack(spacing: 4) {
                Text(Copy.soloDistancePrompt)
                    .font(.makanDisplay(24))
                    .foregroundStyle(Color.kicap)
                Text(Copy.soloDistanceSubtext)
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
            }

            HStack(spacing: 12) {
                ForEach(SoloViewModel.distanceOptions, id: \.km) { option in
                    MakanChoiceTile(
                        illustration: option.illustration,
                        label: option.label,
                        subtext: option.subtext,
                        isSelected: viewModel.maxDistanceKm == option.km
                    ) {
                        UIImpactFeedbackGenerator(style: .light).impactOccurred()
                        viewModel.maxDistanceKm = option.km
                    }
                }
            }
            .padding(.horizontal)

            Spacer().frame(height: 8)

            MakanPrimaryButton(title: Copy.soloCTA) {
                startThinking()
            }
            .padding(.horizontal)
        }
    }

    // MARK: - Thinking transition

    private var thinkingView: some View {
        VStack(spacing: 20) {
            MascotView(mood: .thinking, caption: Copy.thinking, size: 88)
            ThinkingChecklist()
        }
        .padding(.top, 60)
    }

    private static let minimumThinkingDuration: Duration = .milliseconds(700)

    private func startThinking() {
        guard case let .authorized(coordinate) = locationService.state else { return }

        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
        isThinking = true

        Task {
            let start = ContinuousClock.now
            await viewModel.decide(coordinate: coordinate)
            let elapsed = ContinuousClock.now - start
            if elapsed < Self.minimumThinkingDuration {
                try? await Task.sleep(for: Self.minimumThinkingDuration - elapsed)
            }
            router.push(.soloResult)
            isThinking = false
        }
    }

    private func advance() {
        if step < totalSteps - 1 {
            goingForward = true
            step += 1
        }
    }

    private func goBack() {
        if step > 0 {
            goingForward = false
            step -= 1
        }
    }
}

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
