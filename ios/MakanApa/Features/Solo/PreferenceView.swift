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
    @State private var thinkingTask: Task<Void, Never>?

    private let totalSteps = 3

    var body: some View {
        VStack(spacing: 0) {
            if !isThinking {
                PreferenceProgressHeader(step: step) {
                    if step == 0 { router.pop() } else { goBack() }
                }
            }

            Group {
                if isThinking {
                    PreferenceLoadingView(
                        mood: selectedMoodLabel,
                        budget: selectedBudgetLabel,
                        distance: "Within \(viewModel.maxDistanceKm.formatted()) km",
                        onCancel: cancelThinking
                    )
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
            .padding(.top, isThinking ? 0 : 24)
            .animation(reduceMotion ? .easeInOut(duration: 0.2) : .spring(response: 0.4, dampingFraction: 0.85), value: step)
            .animation(.easeInOut(duration: 0.2), value: isThinking)

        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            if !isThinking {
                PreferenceActionFooter(
                    title: step == 2 ? "Find my makan" : "Continue",
                    note: footerNote,
                    isEnabled: step != 0 || canContinueMood,
                    action: { if step == 2 { startThinking() } else { advance() } }
                )
            }
        }
        .sensoryFeedback(.selection, trigger: viewModel.selectedMoodTags)
        .sensoryFeedback(.selection, trigger: choseAnything)
        .sensoryFeedback(.selection, trigger: viewModel.budgetMax)
        .sensoryFeedback(.selection, trigger: viewModel.maxDistanceKm)
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
        .onDisappear {
            // A swipe-back mid-request doesn't tear down this view's plain `Task {}` on its
            // own — without cancelling here, a slow Google response resolves after the user
            // has already navigated away and pushes .soloResult onto whatever screen they're
            // on next.
            thinkingTask?.cancel()
        }
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

    private var footerNote: String {
        switch step {
        case 0: canContinueMood ? "Next up, your budget" : "Pick a mood, or leave it to us"
        case 1: "One last thing, how far?"
        default: "We'll take it from here."
        }
    }

    private var selectedMoodLabel: String {
        SoloViewModel.moodOptions.first { viewModel.selectedMoodTags.contains($0.tag) }?.label
            ?? "Anything lah"
    }

    private var selectedBudgetLabel: String {
        SoloViewModel.budgetOptions.first { $0.tier == viewModel.budgetMax }
            .map { "\($0.amount) per person" } ?? "No budget limit"
    }

    // MARK: - Step 1: Budget

    private var budgetStep: some View {
        VStack(alignment: .leading, spacing: 24) {
            PreferenceStepHeading(title: "What's the budget?", subtitle: "Per person ya. Good makan at every budget.")

            VStack(spacing: 12) {
                ForEach(SoloViewModel.budgetOptions, id: \.tier) { option in
                    PreferenceChoiceRow(
                        illustration: option.illustration,
                        title: option.amount,
                        subtitle: option.label,
                        isSelected: viewModel.budgetMax == option.tier
                    ) {
                        viewModel.budgetMax = option.tier
                    }
                }
            }

            PreferenceChoiceRow(
                symbol: "dice",
                title: "Anything lah",
                subtitle: "No budget limit. Janji sedap.",
                isSelected: viewModel.budgetMax == nil,
                isSecondary: true
            ) {
                viewModel.budgetMax = nil
            }
        }
        .padding(.horizontal, 24)
    }

    // MARK: - Step 2: Distance

    private var distanceStep: some View {
        VStack(alignment: .leading, spacing: 24) {
            PreferenceStepHeading(title: "How far to jalan?", subtitle: "Stay nearby or go a little further for good food.")

            VStack(spacing: 12) {
                ForEach(SoloViewModel.distanceOptions, id: \.km) { option in
                    PreferenceChoiceRow(
                        illustration: option.illustration,
                        title: "Within \(option.km.formatted()) km",
                        subtitle: option.subtext,
                        isSelected: viewModel.maxDistanceKm == option.km
                    ) {
                        viewModel.maxDistanceKm = option.km
                    }
                }
            }

            Label("Distance from your current location.", systemImage: "location")
                .font(.footnote)
                .foregroundStyle(Color.kicap.opacity(0.65))
                .padding(.horizontal, 4)
        }
        .padding(.horizontal, 24)
    }

    // MARK: - Thinking transition

    private static let minimumThinkingDuration: Duration = .milliseconds(700)

    private func startThinking() {
        guard case let .authorized(coordinate) = locationService.state else { return }

        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
        isThinking = true

        thinkingTask = Task {
            let start = ContinuousClock.now
            await viewModel.decide(coordinate: coordinate)
            let elapsed = ContinuousClock.now - start
            if elapsed < Self.minimumThinkingDuration {
                try? await Task.sleep(for: Self.minimumThinkingDuration - elapsed)
            }
            guard !Task.isCancelled else { return }
            router.push(.soloResult)
            isThinking = false
        }
    }

    private func cancelThinking() {
        thinkingTask?.cancel()
        thinkingTask = nil
        isThinking = false
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
