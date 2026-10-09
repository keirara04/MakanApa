import SwiftUI
import UIKit
import CoreLocation

private struct PressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1.0)
            .animation(Motion.quick, value: configuration.isPressed)
    }
}

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService
    @Environment(SoloViewModel.self) private var soloViewModel
    @State private var showSettings = false
    @State private var isQuickPicking = false
    @State private var vibeFollowUp: PendingVibePrompt?
    @State private var pendingDeepLink = PendingDeepLink.shared
    @Environment(\.scenePhase) private var scenePhase
    @State private var quickPickTask: Task<Void, Never>?

    private static let minimumThinkingDuration: Duration = .milliseconds(700)
    private var recentStore = RecentDecisionStore.shared
    private var upgradeNudge = GuestUpgradeNudge.shared

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
        .onChange(of: pendingDeepLink.quickPickRequest, initial: true) { _, request in
            // A generic mealtime nudge (or "Pick something else") asked for a one-tap pick.
            guard let request else { return }
            pendingDeepLink.quickPickRequest = nil
            if let nudgeId = request.nudgeId {
                Task { _ = try? await APIClient.nudgeEvent(nudgeId: nudgeId, event: "quick_pick_started") }
            }
            startQuickPick()
        }
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
                            .foregroundStyle(Color.kicap.opacity(0.45))
                            .font(.system(size: 18))
                            .frame(width: 44, height: 44)
                            .contentShape(Rectangle())
                    }
                    .accessibilityLabel("Settings")
                }

                greeting

                ContextStrip()

                VStack(spacing: 12) {
                    quickPickCard
                    chooseCravingCard
                }

                if let reason = upgradeNudge.activeReason {
                    GuestUpgradeCard(reason: reason)
                        .transition(.opacity.combined(with: .scale(scale: 0.97)))
                }

                recentSection
            }
            .padding()
            .frame(maxWidth: 540)
            .frame(maxWidth: .infinity)
        }
        .scrollIndicators(.hidden)
    }

    // MARK: - Greeting

    /// Nasi sits beside the question instead of at the bottom of the page — the mascot is the
    /// brand, so it belongs where the eye lands first. Held still: Home is seen too often for a
    /// looping bob.
    private var greeting: some View {
        HStack(alignment: .center, spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                Text(Copy.homeGreeting)
                    .font(.makanDisplay(34))
                    .foregroundStyle(Color.kicap)
                Text(Copy.homeSubtext)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap.opacity(0.6))
            }
            .accessibilityElement(children: .combine)

            Spacer(minLength: 0)

            MascotView(mood: .wave, size: 92)
        }
        .padding(.top, 8)
    }

    // MARK: - Decide entry points

    /// Zero questions: "Anything" with the budget/distance used last time. The full
    /// preference flow is still one tap below it for when the user actually has a craving.
    private var quickPickCard: some View {
        Button {
            startQuickPick()
        } label: {
            HStack(spacing: 16) {
                Image(systemName: "dice.fill")
                    .font(.system(size: 22, weight: .semibold))
                    .foregroundStyle(.white)
                    .frame(width: 52, height: 52)
                    .background(.white.opacity(0.18), in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 8) {
                    Text(Copy.quickPickTitle)
                        .font(.makanBody(20))
                        .fontWeight(.heavy)
                    quickPickChips
                }
                .foregroundStyle(.white)

                Spacer(minLength: 0)

                Image(systemName: "chevron.right")
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundStyle(.white.opacity(0.8))
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 20)
            .background(Color.sambalRed, in: .card)
            .shadow(color: Color.kicap.opacity(0.12), radius: 8, y: 4)
        }
        .buttonStyle(PressableCardStyle())
        .accessibilityHint(Copy.quickPickHint)
    }

    /// Falls back to a vertical stack when the chips don't fit on one line (small phones,
    /// large text) instead of wrapping mid-chip.
    private var quickPickChips: some View {
        let parts = soloViewModel.quickPickSummaryParts
        let chips = ForEach(parts, id: \.self) { part in
            Text(part)
                .font(.makanBody(13))
                .monospacedDigit()
                .lineLimit(1)
                .padding(.horizontal, 10)
                .padding(.vertical, 4)
                .background(.white.opacity(0.18), in: Capsule())
        }
        return ViewThatFits(in: .horizontal) {
            HStack(spacing: 6) { chips }
            VStack(alignment: .leading, spacing: 6) { chips }
        }
        .accessibilityElement(children: .combine)
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
                Image(systemName: "slider.horizontal.3")
                    .font(.system(size: 22, weight: .semibold))
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 52, height: 52)
                    .background(Color.sambalRed.opacity(0.1), in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text(Copy.chooseCravingTitle)
                        .font(.makanBody(18))
                        .fontWeight(.heavy)
                    Text(Copy.chooseCravingSubtitle)
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap.opacity(0.6))
                }
                .foregroundStyle(Color.kicap)

                Spacer(minLength: 0)

                Image(systemName: "chevron.right")
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundStyle(Color.kicap.opacity(0.4))
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 18)
            .background(Color.kicap.opacity(0.06), in: .card)
        }
        .buttonStyle(PressableCardStyle())
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
            Text(Copy.recentTitle)
                .font(.makanBody(13))
                .fontWeight(.semibold)
                .foregroundStyle(Color.kicap.opacity(0.6))
                .accessibilityAddTraits(.isHeader)

            if recentStore.decisions.isEmpty {
                Text(Copy.recentEmpty)
                    .font(.makanBody(14))
                    .foregroundStyle(Color.kicap.opacity(0.5))
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .padding(16)
                    .background(Color.kicap.opacity(0.04), in: .row)
            } else {
                VStack(spacing: 8) {
                    ForEach(recentStore.decisions.prefix(3)) { decision in
                        recentCard(for: decision)
                    }
                }
            }
        }
        .animation(Motion.standard, value: recentStore.decisions)
    }

    private func recentCard(for decision: RecentDecision) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            pickAgain(decision)
        } label: {
            HStack(spacing: 12) {
                Image(systemName: categorySymbol(for: decision.foodCategory))
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 36, height: 36)
                    .background(Color.sambalRed.opacity(0.1), in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text(decision.name)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)
                        .lineLimit(1)
                    Text("\(relativeDay(decision.timestamp)) · \(sourceLabel(decision.source))")
                        .font(.makanBody(12))
                        .foregroundStyle(Color.kicap.opacity(0.55))
                }

                Spacer(minLength: 0)

                Image(systemName: "arrow.up.right")
                    .foregroundStyle(Color.kicap.opacity(0.4))
                    .font(.system(size: 13, weight: .semibold))
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .background(Color.kicap.opacity(0.05), in: .row)
            .contentShape(.row)
        }
        .buttonStyle(PressableCardStyle())
        .accessibilityHint(Copy.openInMaps)
        .contextMenu {
            Button(Copy.openInMaps, systemImage: "map") { pickAgain(decision) }
            Button(Copy.removeFromRecent, systemImage: "trash", role: .destructive) {
                recentStore.remove(id: decision.id)
            }
        }
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
        case "nearby": return Copy.recentSourceNearby
        case "search": return Copy.recentSourceSearch
        default: return Copy.recentSourceDecide
        }
    }

    private func relativeDay(_ date: Date) -> String {
        if Calendar.current.isDateInToday(date) { return Copy.recentToday }
        if Calendar.current.isDateInYesterday(date) { return Copy.recentYesterday }
        let formatter = RelativeDateTimeFormatter()
        formatter.dateTimeStyle = .named
        formatter.formattingContext = .beginningOfSentence
        return formatter.localizedString(for: date, relativeTo: Date())
    }

    /// SF Symbols, not emoji — icons are chrome. Categories arrive both bare ("sushi") and with
    /// Google's "_restaurant" suffix ("sushi_restaurant"), so match on the stem.
    private func categorySymbol(for foodCategory: String?) -> String {
        let stem = foodCategory?.replacingOccurrences(of: "_restaurant", with: "") ?? ""
        switch stem {
        case "burger", "sandwich", "fast_food", "pizza", "chicken", "western":
            return "takeoutbag.and.cup.and.straw.fill"
        case "sushi", "seafood", "japanese":
            return "fish.fill"
        case "cafe", "breakfast", "drinks", "coffee_shop":
            return "cup.and.saucer.fill"
        case "dessert", "bakery":
            return "birthday.cake.fill"
        default:
            return "fork.knife"
        }
    }
}
