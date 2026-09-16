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
            MakanApaTopBar(
                trailing: isThinking ? nil : "\(step + 1)/\(totalSteps)",
                onBack: (!isThinking && step > 0) ? { goBack() } : nil
            )

            if !isThinking {
                progressTrack
                    .padding(.horizontal)
                    .padding(.top, 10)
            }

            Group {
                if isThinking {
                    thinkingView
                } else {
                    stepContent
                        .id(step)
                        .transition(stepTransition)
                }
            }
            .padding(.top, 28)
            .animation(reduceMotion ? .easeInOut(duration: 0.2) : .spring(response: 0.4, dampingFraction: 0.85), value: step)
            .animation(.easeInOut(duration: 0.2), value: isThinking)

            Spacer()
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

    private var progressTrack: some View {
        GeometryReader { geo in
            ZStack(alignment: .leading) {
                Capsule().fill(Color.kicap.opacity(0.08))
                Capsule().fill(Color.sambalRed)
                    .frame(width: geo.size.width * CGFloat(step + 1) / CGFloat(totalSteps))
                    .animation(reduceMotion ? .easeInOut(duration: 0.2) : .spring(response: 0.35, dampingFraction: 0.8), value: step)
            }
        }
        .frame(height: 4)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Step \(step + 1) of \(totalSteps)")
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
                advance()
            } label: {
                Text(Copy.soloAnythingLah)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap)
                    .padding(.horizontal, 18)
                    .padding(.vertical, 10)
                    .overlay(
                        Capsule().strokeBorder(Color.kicap.opacity(0.4), style: StrokeStyle(lineWidth: 1.5, dash: [4, 3]))
                    )
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
                        emoji: option.amount,
                        label: option.label,
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
                Text(Copy.soloAnythingLah)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap)
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
            Text(Copy.soloDistancePrompt)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)

            HStack(spacing: 12) {
                ForEach(SoloViewModel.distanceOptions, id: \.km) { option in
                    MakanChoiceTile(
                        emoji: option.emoji,
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
        VStack(spacing: 16) {
            MascotView(mood: .thinking, caption: Copy.thinking, size: 88)
            ThinkingDots()
        }
        .padding(.top, 60)
    }

    private static let minimumThinkingDuration: Duration = .milliseconds(500)

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

/// Three-dot ellipsis, staggered opacity loop — reads as "deciding," not a network spinner.
private struct ThinkingDots: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var animate = false

    var body: some View {
        HStack(spacing: 6) {
            ForEach(0..<3, id: \.self) { index in
                Circle()
                    .fill(Color.sambalRed)
                    .frame(width: 7, height: 7)
                    .opacity(reduceMotion ? 1 : (animate ? 1 : 0.25))
                    .animation(
                        reduceMotion ? nil : .easeInOut(duration: 0.5).repeatForever(autoreverses: true).delay(Double(index) * 0.15),
                        value: animate
                    )
            }
        }
        .onAppear { animate = true }
    }
}
