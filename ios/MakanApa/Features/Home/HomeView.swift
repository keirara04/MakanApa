import SwiftUI
import CoreLocation

private struct PressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1.0)
            .animation(Motion.quick, value: configuration.isPressed)
    }
}

/// List rows highlight on press instead of scaling — matches how iOS lists behave.
private struct RowHighlightStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .background(configuration.isPressed ? Color.hairline : .clear)
            .animation(Motion.quick, value: configuration.isPressed)
    }
}

/// Sections rise in once per app launch, staggered — never on tab switches (Home is seen too
/// often for that). Reduce Motion drops the offset and keeps a plain fade.
private struct Entrance: ViewModifier {
    let index: Int
    let entered: Bool
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func body(content: Content) -> some View {
        content
            .opacity(entered ? 1 : 0)
            .offset(y: entered || reduceMotion ? 0 : 8)
            .animation(Motion.standard.delay(Double(index) * 0.04), value: entered)
    }
}

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService
    @Environment(SoloViewModel.self) private var soloViewModel
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var showSettings = false
    @State private var isQuickPicking = false
    @State private var vibeFollowUp: PendingVibePrompt?
    @State private var pendingDeepLink = PendingDeepLink.shared
    @Environment(\.scenePhase) private var scenePhase
    @State private var quickPickTask: Task<Void, Never>?
    @State private var entered = HomeView.hasEntered
    @State private var quickPickTaps = 0
    @State private var recentTaps = 0

    @MainActor private static var hasEntered = false
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
        .background(Color.nasiCream)
        .sensoryFeedback(.impact(weight: .medium), trigger: quickPickTaps)
        .sensoryFeedback(.impact(weight: .light), trigger: recentTaps)
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
        .onAppear {
            checkVibeFollowUp()
            Self.hasEntered = true
            entered = true
        }
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
            VStack(spacing: 32) {
                VStack(spacing: 16) {
                    header
                    greeting
                }
                .modifier(Entrance(index: 0, entered: entered))

                ContextStrip()

                VStack(spacing: 12) {
                    quickPickCard
                    chooseCravingCard
                }
                .modifier(Entrance(index: 1, entered: entered))

                if let reason = upgradeNudge.activeReason {
                    GuestUpgradeCard(reason: reason)
                        .transition(.opacity.combined(with: .scale(scale: 0.97)))
                }

                recentSection
                    .modifier(Entrance(index: 2, entered: entered))
            }
            .padding(.horizontal, 20)
            .padding(.top, 8)
            .padding(.bottom, 24)
            .frame(maxWidth: 540)
            .frame(maxWidth: .infinity)
        }
        .scrollIndicators(.hidden)
    }

    // MARK: - Header

    private var header: some View {
        HStack {
            (Text("Makan").foregroundStyle(Color.kicap) + Text("Apa?").foregroundStyle(Color.sambalRed))
                .font(.makanDisplay(17))

            Spacer()

            Button {
                showSettings = true
            } label: {
                Image(systemName: "gearshape")
                    .font(.title3)
                    .foregroundStyle(Color.kicapSecondary)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .accessibilityLabel("Settings")
        }
    }

    // MARK: - Greeting

    private var greeting: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(Copy.homeGreeting(hour: Calendar.current.component(.hour, from: .now)))
                .font(.largeTitle.weight(.bold))
                .foregroundStyle(Color.kicap)
            Text(Copy.homeSubtext)
                .font(.subheadline)
                .foregroundStyle(Color.kicapSecondary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
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
                    .font(.title3.weight(.semibold))
                    .symbolEffect(.bounce, value: quickPickTaps)
                    .frame(width: 52, height: 52)
                    .background(.white.opacity(0.18), in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 4) {
                    Text(Copy.quickPickTitle)
                        .font(.title3.weight(.bold))
                    Text(soloViewModel.quickPickSummaryParts.joined(separator: " · "))
                        .font(.subheadline)
                        .monospacedDigit()
                        .lineLimit(2)
                }

                Spacer(minLength: 0)

                Image(systemName: "chevron.right")
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(.white.opacity(0.8))
            }
            .foregroundStyle(.white)
            .padding(20)
            .background(Color.sambalRed, in: .card)
            .shadow(color: Color.sambalRed.opacity(0.28), radius: 16, y: 8)
        }
        .buttonStyle(PressableCardStyle())
        .accessibilityHint(Copy.quickPickHint)
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
                    .font(.title3.weight(.semibold))
                    .foregroundStyle(Color.kicap)
                    .frame(width: 52, height: 52)
                    .background(Color.nasiCream, in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text(Copy.chooseCravingTitle)
                        .font(.headline)
                        .foregroundStyle(Color.kicap)
                    Text(Copy.chooseCravingSubtitle)
                        .font(.subheadline)
                        .foregroundStyle(Color.kicapSecondary)
                }

                Spacer(minLength: 0)

                Image(systemName: "chevron.right")
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)
            }
            .padding(20)
            .background(Color.surface, in: .card)
            .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
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

        quickPickTaps += 1
        soloViewModel.prepareQuickPick()
        withAnimation(reduceMotion ? .easeInOut(duration: 0.2) : Motion.playful) {
            isQuickPicking = true
        }

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
        guard isQuickPicking else { return }
        withAnimation(reduceMotion ? .easeInOut(duration: 0.15) : Motion.standard) {
            isQuickPicking = false
        }
    }

    // MARK: - Recent

    private var recentSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(Copy.recentTitle)
                .font(.footnote.weight(.semibold))
                .foregroundStyle(Color.kicapSecondary)
                .padding(.leading, 4)
                .accessibilityAddTraits(.isHeader)

            Group {
                if recentStore.decisions.isEmpty {
                    Text(Copy.recentEmpty)
                        .font(.subheadline)
                        .foregroundStyle(Color.kicapSecondary)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(16)
                } else {
                    // One grouped container with hairlines, not a stack of floating cards.
                    VStack(spacing: 0) {
                        ForEach(Array(recentStore.decisions.prefix(3).enumerated()), id: \.element.id) { index, decision in
                            if index > 0 {
                                Color.hairline
                                    .frame(height: 1)
                                    .padding(.leading, 64)
                            }
                            recentRow(for: decision)
                        }
                    }
                }
            }
            .background(Color.surface)
            .clipShape(.card)
            .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
        }
        .animation(Motion.standard, value: recentStore.decisions)
    }

    private func recentRow(for decision: RecentDecision) -> some View {
        Button {
            recentTaps += 1
            pickAgain(decision)
        } label: {
            HStack(spacing: 12) {
                Image(systemName: categorySymbol(for: decision.foodCategory))
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(Color.kicap)
                    .frame(width: 36, height: 36)
                    .background(Color.nasiCream, in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text(decision.name)
                        .font(.body.weight(.medium))
                        .foregroundStyle(Color.kicap)
                        .lineLimit(1)
                    Text(recentDetail(for: decision))
                        .font(.footnote)
                        .foregroundStyle(Color.kicapSecondary)
                        .lineLimit(1)
                }

                Spacer(minLength: 0)

                Image(systemName: "arrow.up.right")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .frame(minHeight: 60)
            .contentShape(Rectangle())
        }
        .buttonStyle(RowHighlightStyle())
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

    /// "Coffee Shop · ≈ RM10/person · Tue" — what the place is, not which tab it came from.
    private func recentDetail(for decision: RecentDecision) -> String {
        [
            categoryLabel(for: decision.foodCategory),
            PricePresentation.approximateSpendLabel(for: decision.priceLevel),
            relativeDay(decision.timestamp),
        ]
        .compactMap { $0 }
        .joined(separator: " · ")
    }

    private func relativeDay(_ date: Date) -> String {
        let calendar = Calendar.current
        if calendar.isDateInToday(date) { return Copy.recentToday }
        if calendar.isDateInYesterday(date) { return Copy.recentYesterday }
        if let days = calendar.dateComponents([.day], from: date, to: .now).day, days < 7 {
            return date.formatted(.dateTime.weekday(.abbreviated))
        }
        return date.formatted(.dateTime.day().month(.abbreviated))
    }

    /// Categories arrive both bare ("sushi") and with Google's "_restaurant" suffix
    /// ("sushi_restaurant"), so match on the stem.
    private func categoryStem(_ foodCategory: String?) -> String {
        foodCategory?.replacingOccurrences(of: "_restaurant", with: "") ?? ""
    }

    /// Nil for the generic "restaurant" — it says nothing the row doesn't already.
    private func categoryLabel(for foodCategory: String?) -> String? {
        let stem = categoryStem(foodCategory)
        guard !stem.isEmpty, stem != "restaurant" else { return nil }
        return stem.replacingOccurrences(of: "_", with: " ").capitalized
    }

    /// SF Symbols, not emoji — icons are chrome.
    private func categorySymbol(for foodCategory: String?) -> String {
        switch categoryStem(foodCategory) {
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
