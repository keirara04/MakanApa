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
        .sensoryFeedback(.selection, trigger: viewModel.cravingSelection)
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

    // MARK: - Step 0: Craving

    /// Derived directly from `cravingSelection` rather than a parallel `@State` string — typing
    /// is the *only* path that writes `.custom(...)` (via this binding's setter, which only
    /// fires on real keystrokes). Tapping a card/Quick/Healthy/"Anything lah" sets
    /// `cravingSelection` directly; the text field's displayed value then reads back as empty
    /// automatically (the getter returns "" for any non-`.custom` case) with no separate clear
    /// step that could race with — and clobber — the tap's own selection.
    private var customCravingTextBinding: Binding<String> {
        Binding(
            get: {
                if case .custom(let text) = viewModel.cravingSelection { return text }
                return ""
            },
            set: { newValue in
                viewModel.cravingSelection = newValue.isEmpty ? nil : .custom(newValue)
            }
        )
    }

    private var canContinueMood: Bool {
        switch viewModel.cravingSelection {
        case .tag, .anything: return true
        case .custom(let text): return !text.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        case nil: return false
        }
    }

    private var moodStep: some View {
        MoodSelectionView(
            cravingSelection: viewModel.cravingSelection,
            customText: customCravingTextBinding,
            onSelectTag: { tag in
                viewModel.cravingSelection = .tag(tag)
            },
            onAnything: {
                viewModel.cravingSelection = .anything
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
        switch viewModel.cravingSelection {
        case .tag(let tag):
            return SoloViewModel.moodOptions.first { $0.tag == tag }?.label ?? "Anything lah"
        case .custom(let text):
            let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
            return trimmed.isEmpty ? "Anything lah" : trimmed
        case .anything, nil:
            return "Anything lah"
        }
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

            lensRow
        }
        .padding(.horizontal, 24)
    }

    /// Makan Brain lenses — an explicit "how do I want to decide today", instead of the app
    /// guessing (e.g. assuming month-end means broke). Optional; tap again to clear.
    private var lensRow: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Today I want…")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(Lens.allCases) { lens in
                        let selected = viewModel.lens == lens
                        Button {
                            viewModel.lens = selected ? nil : lens
                        } label: {
                            Text("\(lens.emoji) \(lens.label(community: communityName))")
                                .font(.makanBody(13))
                                .foregroundStyle(selected ? .white : Color.kicap)
                                .padding(.horizontal, 12)
                                .frame(minHeight: 36)
                                .background(selected ? Color.sambalRed : Color.kicap.opacity(0.06))
                                .clipShape(Capsule())
                        }
                        .buttonStyle(.plain)
                        .accessibilityAddTraits(selected ? .isSelected : [])
                    }
                }
            }
        }
        .sensoryFeedback(.selection, trigger: viewModel.lens)
    }

    private var communityName: String? {
        guard case .authenticated(let user) = AuthStore.shared.session else { return nil }
        return user.university ?? user.area
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

            ContextStrip()
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
