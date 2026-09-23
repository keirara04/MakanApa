import SwiftUI
import UIKit
import CoreLocation

private struct PressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1.0)
            .animation(.spring(response: 0.25, dampingFraction: 0.7), value: configuration.isPressed)
    }
}

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService
    @Environment(SoloViewModel.self) private var soloViewModel
    @State private var showGengComingSoon = false
    @State private var showSettings = false
    @State private var isQuickPicking = false
    @State private var vibeFollowUp: PendingVibePrompt?
    @Environment(\.scenePhase) private var scenePhase
    @State private var quickPickTask: Task<Void, Never>?

    private static let minimumThinkingDuration: Duration = .milliseconds(700)
    private var recentStore = RecentDecisionStore.shared

    var body: some View {
        ZStack {
            if isQuickPicking {
                PreferenceLoadingView(
                    mood: Copy.anythingLabel,
                    budget: quickPickBudgetLabel,
                    distance: "Within \(soloViewModel.maxDistanceKm.formatted()) km",
                    onCancel: cancelQuickPick
                )
                .transition(.opacity)
            } else {
                homeContent
                    .transition(.opacity)
            }
        }
        .animation(.easeInOut(duration: 0.2), value: isQuickPicking)
        .background(Color.nasiCream)
        .alert(Copy.gengComingSoon, isPresented: $showGengComingSoon) {
            Button("Okay", role: .cancel) {}
        }
        .sheet(isPresented: $showSettings) {
            SettingsView()
        }
        .sheet(item: $vibeFollowUp) { prompt in
            VibeFollowUpSheet(
                prompt: prompt,
                onAnswer: { tag in
                    PendingVibePromptStore.shared.answer(prompt, with: tag)
                    vibeFollowUp = nil
                },
                onSkip: {
                    PendingVibePromptStore.shared.dismiss()
                    vibeFollowUp = nil
                }
            )
            .presentationDetents([.height(260)])
        }
        .onAppear { checkVibeFollowUp() }
        .onChange(of: scenePhase) { _, phase in
            if phase == .active { checkVibeFollowUp() }
        }
        .onDisappear { cancelQuickPick() }
    }

    private func checkVibeFollowUp() {
        guard vibeFollowUp == nil, !isQuickPicking else { return }
        vibeFollowUp = PendingVibePromptStore.shared.due()
    }

    private var homeContent: some View {
        // Scrolls so large Dynamic Type sizes and small phones (SE/mini) never clip the recent
        // list or the cards — the old fixed VStack ran out of height.
        ScrollView {
            VStack(spacing: 24) {
                HStack {
                    (Text("Makan").foregroundStyle(Color.kicap) + Text("Apa?").foregroundStyle(Color.sambalRed))
                        .font(.makanDisplay(20))

                    Spacer()

                    Button {
                        showSettings = true
                    } label: {
                        Image(systemName: "gearshape.fill")
                            .foregroundStyle(.secondary)
                            .font(.system(size: 18))
                            .frame(width: 44, height: 44)
                            .contentShape(Rectangle())
                    }
                    .accessibilityLabel("Settings")
                }

                VStack(spacing: 6) {
                    Text(Copy.homeGreeting)
                        .font(.makanDisplay(28))
                        .foregroundStyle(Color.kicap)
                    Text(Copy.homeSubtext)
                        .font(.makanBody(15))
                        .foregroundStyle(.secondary)
                }
                .multilineTextAlignment(.center)
                .padding(.top, 12)

                ContextStrip()

                VStack(spacing: 12) {
                    quickPickCard
                    chooseCravingCard
                    gengRow
                }

                if !recentStore.decisions.isEmpty {
                    recentSection
                }

                MascotView(mood: .idle, size: 120)
                    .padding(.top, 8)
                    .accessibilityHidden(true)
            }
            .padding()
            .frame(maxWidth: 540)
            .frame(maxWidth: .infinity)
        }
        .scrollIndicators(.hidden)
    }

    // MARK: - Decide entry points

    /// Zero questions: "Anything lah" with the budget/distance used last time. The full
    /// preference flow is still one tap below it for when the user actually has a craving.
    private var quickPickCard: some View {
        Button {
            startQuickPick()
        } label: {
            HStack(spacing: 16) {
                Image("SoloIllustration")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 52, height: 52)
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text(Copy.quickPickTitle).font(.makanDisplay(20))
                    Text(soloViewModel.quickPickSummaryForDisplay).font(.makanBody(14))
                }
                .foregroundStyle(.white)

                Spacer()

                Image(systemName: "sparkles")
                    .foregroundStyle(.white.opacity(0.9))
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 22)
            .background(Color.sambalRed)
            .clipShape(RoundedRectangle(cornerRadius: 30))
            .shadow(color: Color.kicap.opacity(0.12), radius: 8, y: 4)
        }
        .buttonStyle(PressableCardStyle())
        .accessibilityHint("Picks somewhere nearby right away using your usual budget and distance")
    }

    private var chooseCravingCard: some View {
        Button {
            if case .authorized = locationService.state {
                router.push(.soloPreferences)
            } else {
                router.push(.locationPermission)
            }
        } label: {
            HStack(spacing: 16) {
                Image(systemName: "fork.knife")
                    .font(.system(size: 22, weight: .semibold))
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 52, height: 52)
                    .background(Color.sambalRed.opacity(0.1))
                    .clipShape(Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text(Copy.chooseCravingTitle).font(.makanDisplay(18))
                    Text(Copy.chooseCravingSubtitle).font(.makanBody(14))
                        .foregroundStyle(.secondary)
                }
                .foregroundStyle(Color.kicap)

                Spacer()

                Image(systemName: "chevron.right")
                    .foregroundStyle(Color.kicap.opacity(0.4))
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 18)
            .background(Color.kicap.opacity(0.06))
            .clipShape(RoundedRectangle(cornerRadius: 26))
        }
        .buttonStyle(PressableCardStyle())
    }

    /// Geng mode isn't built yet — a quiet teaser row, not a full-size card competing with
    /// the two things that actually work.
    private var gengRow: some View {
        Button {
            showGengComingSoon = true
        } label: {
            HStack(spacing: 8) {
                Image("GengIllustration")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 24, height: 24)
                    .accessibilityHidden(true)
                Text(Copy.gengTeaser)
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap.opacity(0.6))
                Text("Soon")
                    .font(.makanBody(10))
                    .foregroundStyle(Color.kicap.opacity(0.5))
                    .padding(.horizontal, 8)
                    .padding(.vertical, 3)
                    .background(Color.kicap.opacity(0.08))
                    .clipShape(Capsule())
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 8)
        }
        .buttonStyle(.plain)
    }

    private var quickPickBudgetLabel: String {
        SoloViewModel.budgetOptions.first { $0.tier == soloViewModel.budgetMax }?.amount ?? Copy.budgetAnythingSubtext
    }

    private func startQuickPick() {
        guard case let .authorized(coordinate) = locationService.state else {
            router.push(.locationPermission)
            return
        }

        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
        soloViewModel.prepareQuickPick()
        isQuickPicking = true

        quickPickTask = Task {
            let start = ContinuousClock.now
            await soloViewModel.decide(coordinate: coordinate)
            // Same floor as the full flow — a very fast answer still reads as "thought about it".
            let elapsed = ContinuousClock.now - start
            if elapsed < Self.minimumThinkingDuration {
                try? await Task.sleep(for: Self.minimumThinkingDuration - elapsed)
            }
            guard !Task.isCancelled else { return }
            router.push(.soloResult)
            isQuickPicking = false
        }
    }

    private func cancelQuickPick() {
        quickPickTask?.cancel()
        quickPickTask = nil
        isQuickPicking = false
    }

    // MARK: - Recent

    private var recentSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Recent")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)

            VStack(spacing: 8) {
                ForEach(recentStore.decisions.prefix(3)) { decision in
                    recentCard(for: decision)
                }
            }
        }
    }

    private func recentCard(for decision: RecentDecision) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            pickAgain(decision)
        } label: {
            HStack(spacing: 12) {
                Text(categoryEmoji(for: decision.foodCategory))
                    .font(.system(size: 22))

                VStack(alignment: .leading, spacing: 2) {
                    Text(decision.name)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)
                    Text("\(relativeDay(decision.timestamp)) · \(sourceLabel(decision.source))")
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                }

                Spacer()

                Image(systemName: "arrow.up.right")
                    .foregroundStyle(.secondary)
                    .font(.system(size: 13, weight: .semibold))
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .background(Color.kicap.opacity(0.05))
            .clipShape(RoundedRectangle(cornerRadius: 18))
        }
        .buttonStyle(PressableCardStyle())
    }

    private func pickAgain(_ decision: RecentDecision) {
        let destination = MapDestination(
            coordinate: CLLocationCoordinate2D(latitude: decision.latitude, longitude: decision.longitude),
            name: decision.name,
            googleMapsURL: nil
        )
        PreferredMapsLauncher.open(destination: destination, provider: MapProviderPreference.current)
    }

    private func sourceLabel(_ source: String) -> String {
        switch source {
        case "nearby": return "Nearby"
        case "search": return "Search"
        default: return "Decide"
        }
    }

    private func relativeDay(_ date: Date) -> String {
        if Calendar.current.isDateInToday(date) { return "Today" }
        if Calendar.current.isDateInYesterday(date) { return "Yesterday" }
        let formatter = RelativeDateTimeFormatter()
        formatter.dateTimeStyle = .named
        return formatter.localizedString(for: date, relativeTo: Date())
    }

    private func categoryEmoji(for foodCategory: String?) -> String {
        switch foodCategory {
        case "burger", "sandwich", "fast_food": return "🍔"
        case "chicken": return "🍗"
        case "pizza": return "🍕"
        case "ramen": return "🍜"
        case "sushi", "seafood": return "🍣"
        case "cafe", "breakfast", "drinks": return "☕️"
        case "dessert", "bakery": return "🍰"
        default: return "🍛"
        }
    }
}
