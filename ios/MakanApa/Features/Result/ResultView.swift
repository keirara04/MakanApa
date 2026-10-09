import SwiftUI
import MapKit
import UIKit

struct ResultView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var viewModel
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    /// One switch for the whole reveal; each piece staggers off it with its own delay.
    @State private var revealed = false
    @State private var revealTick = 0
    @State private var rerollTaps = 0
    @State private var rejectTaps = 0
    @State private var isRerolling = false
    @State private var rerollTask: Task<Void, Never>?
    @State private var photoPage = 0
    @State private var exitEdge: Edge = .leading
    @State private var revealedReasonCount = 0
    @State private var showNotificationPriming = false
    @State private var showTastePrompt = false
    @State private var showingAddMenu = false
    @State private var showTrace = false
    @State private var showingWhatIf = false
    @State private var whatIfEntries: [WhatIfEntry] = []
    @State private var whatIfLoading = false
    @State private var isTuning = false

    private static let minimumRerollDuration: Duration = .milliseconds(700)
    private static let traceReplayLatencyLimit: Duration = .seconds(2)

    var body: some View {
        Group {
            if !isRerolling, viewModel.apiError == nil, let pick = viewModel.currentPick {
                resultContent(for: pick)
            } else {
                VStack(spacing: 20) {
                    if isRerolling {
                        rerollingContent
                    } else if let error = viewModel.apiError {
                        errorContent(for: error)
                    } else {
                        noResultContent
                    }
                    Spacer()
                }
                .padding()
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream)
        // System back (keeps the edge swipe) instead of a hand-rolled bar + wordmark — the pick
        // is the headline here, nothing should sit above it. The photo runs up under the bar.
        .navigationBarTitleDisplayMode(.inline)
        .toolbarBackground(.hidden, for: .navigationBar)
        .sensoryFeedback(.success, trigger: revealTick)
        .sensoryFeedback(.impact(weight: .light), trigger: rerollTaps)
        .sensoryFeedback(.impact(weight: .medium), trigger: rejectTaps)
        .task(id: viewModel.currentPick?.id) {
            guard !isRerolling else { return }
            photoPage = 0
            await runRevealSequence()
        }
        .onChange(of: viewModel.apiError == nil) { _, hasNoError in
            if !hasNoError {
                UINotificationFeedbackGenerator().notificationOccurred(.error)
            }
        }
        .sheet(isPresented: $showingWhatIf) {
            WhatIfSheet(entries: whatIfEntries, isLoading: whatIfLoading) { entry in
                Task { await viewModel.choose(restaurantId: entry.winner.id) }
            }
            .presentationDetents([.medium, .large])
        }
        .sheet(isPresented: $showNotificationPriming) {
            NotificationPrimingView(onFinished: {
                NotificationPrimingState.shared.complete()
                showNotificationPriming = false
            })
            .interactiveDismissDisabled()
        }
        .sheet(isPresented: $showTastePrompt, onDismiss: {
            // Swiped away counts as "Not now" — don't ask on every accept.
            if OnboardingState.shared.shouldAskTaste {
                OnboardingState.shared.finishTastePrompt(saved: false)
            }
        }) {
            TastePromptSheet()
                .presentationDetents([.medium, .large])
        }
        .sheet(isPresented: $showingAddMenu) {
            if let pick = viewModel.currentPick {
                AddPlaceFlow(
                    prefillExisting: ExistingPlaceResult(
                        id: pick.id,
                        name: pick.name,
                        address: nil,
                        foodCategory: pick.foodCategory,
                        priceLevel: pick.priceLevel,
                        distanceKm: pick.distanceKm,
                        latitude: pick.latitude,
                        longitude: pick.longitude
                    ),
                    prefillShowMenuSection: true
                )
            }
        }
    }

    // MARK: - After accept

    /// The vibe question is now asked later, from Home (see PendingVibePromptStore). The accept
    /// moment is instead where a first-time user is asked about notifications — right after
    /// the app has actually been useful, not before they've even signed in.
    private func afterAccept() {
        // One ask per accept: notifications on the first, cravings on the next.
        if !NotificationPrimingState.shared.hasSeenPriming {
            showNotificationPriming = true
        } else if OnboardingState.shared.shouldAskTaste {
            showTastePrompt = true
        }
    }

    // MARK: - Result

    /// The photo is the hero, the name and one details line sit under it, and the decision lives
    /// in a bar pinned above the tab bar — always reachable, never at the end of a long scroll.
    /// While a real thinking trace replays, it takes the whole page; then the result reveals.
    @ViewBuilder
    private func resultContent(for pick: RecommendationResponse.Recommendation) -> some View {
        if showTrace, let trace = pick.thinkingTrace, !trace.isEmpty {
            ThinkingTraceView(lines: trace)
                .padding(.horizontal, 32)
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .transition(.opacity)
        } else {
            ScrollView {
                VStack(alignment: .leading, spacing: 0) {
                    heroPhoto(for: pick)
                        .reveal(revealed, delay: 0)

                    VStack(alignment: .leading, spacing: 24) {
                        header(for: pick)

                        VStack(alignment: .leading, spacing: 16) {
                            if let reasons = pick.reasons, !reasons.isEmpty {
                                KenapaNiSection(
                                    reasons: reasons,
                                    decidingFactor: pick.decidingFactor,
                                    revealedCount: revealedReasonCount,
                                    hasWhatIf: pick.hasWhatIf == true,
                                    onWhatIf: openWhatIf
                                )
                            } else {
                                reasonChips
                            }

                            if let from = viewModel.rerolledAwayFrom {
                                VStack(spacing: 4) {
                                    Text("Skipped \(from)")
                                        .font(.footnote)
                                        .foregroundStyle(Color.kicapSecondary)
                                    WhyNotChips { reason, detail in viewModel.sendWhyNot(reason, detail: detail) }
                                }
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 4)
                            }

                            if let shareUrl = pick.shareUrl {
                                SendToGengButton(
                                    restaurantId: pick.id,
                                    shareUrl: shareUrl,
                                    message: SendToGengButton.message(name: pick.name, whereText: pick.foodCategory.map { $0.replacingOccurrences(of: "_", with: " ").capitalized }, distanceKm: pick.distanceKm)
                                ) {
                                    viewModel.logInteraction("shared")
                                }
                            }

                            if let adjustment = viewModel.searchWider {
                                SearchWiderBanner(message: viewModel.searchWiderMessage ?? "Nothing better nearby. Search a bit wider?", adjustment: adjustment) {
                                    Task { await viewModel.acceptSearchWider() }
                                }
                            } else if viewModel.canTune {
                                TuneRow(used: viewModel.tunesUsed) { direction in tune(direction) }
                                    .disabled(isTuning)
                                    .opacity(isTuning ? 0.5 : 1)
                            }

                            if !pick.menuItems.isEmpty {
                                menuSection(for: pick)
                            } else {
                                menuNudge
                            }

                            if let halal = pick.halal {
                                HalalVerificationSection(restaurantId: pick.id, restaurantName: pick.name, halal: halal)
                            }

                            if !pick.reviews.isEmpty {
                                reviewsSection(for: pick)
                            }

                            if pick.photos.contains(where: { !$0.authorAttributions.isEmpty }) || !pick.reviews.isEmpty {
                                Text("Photo & reviews from Google Maps")
                                    .font(.caption2)
                                    .foregroundStyle(Color.kicapSecondary)
                                    .frame(maxWidth: .infinity)
                            }
                        }
                        .reveal(revealed, delay: 0.25)
                    }
                    .padding(.horizontal, 20)
                    .padding(.top, 20)
                    .padding(.bottom, 24)
                }
            }
            .scrollIndicators(.hidden)
            .ignoresSafeArea(edges: .top)
            .safeAreaInset(edge: .bottom, spacing: 0) {
                actionBar(for: pick)
                    .reveal(revealed, delay: 0.1)
            }
            .transition(.asymmetric(
                insertion: .opacity,
                removal: reduceMotion ? .opacity : .move(edge: exitEdge).combined(with: .opacity)
            ))
        }
    }

    // MARK: - Header

    private func header(for pick: RecommendationResponse.Recommendation) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 6) {
                Label(pick.fatigue == true ? Copy.resultFatigue : Copy.pickedForYou, systemImage: "checkmark.circle.fill")
                    .foregroundStyle(Color.sambalRed)
                if let fit = pick.fit {
                    Text("·")
                        .foregroundStyle(Color.kicapSecondary)
                        .accessibilityHidden(true)
                    Label(fit.label, systemImage: fitSymbol(fit))
                        .foregroundStyle(Color.kicapSecondary)
                }
            }
            .font(.footnote.weight(.semibold))
            .reveal(revealed, delay: 0.05)

            Text(pick.name)
                .font(.title.weight(.bold))
                .foregroundStyle(Color.kicap)
                .lineLimit(3)
                .minimumScaleFactor(0.8)
                .accessibilityAddTraits(.isHeader)
                .reveal(revealed, delay: 0.1)

            Text(detailsLine(for: pick))
                .font(.subheadline)
                .monospacedDigit()
                .foregroundStyle(Color.kicapSecondary)
                .reveal(revealed, delay: 0.15)

            if let halal = pick.halal {
                HalalBadge(display: halal.display)
                    .padding(.top, 4)
                    .reveal(revealed, delay: 0.2)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    /// "Cafe · ≈ RM10/person · 12 min walk · Open until 10 PM" — unknown hours are left out, not
    /// announced.
    private func detailsLine(for pick: RecommendationResponse.Recommendation) -> String {
        let kind = categorySubtitleLabel(pick.foodCategory) ?? pick.cuisines.first?.capitalized
        let distance = pick.distanceKm < 1.5
            ? "\(walkingMinutes(for: pick.distanceKm)) min walk"
            : "\(pick.distanceKm.formatted(.number.precision(.fractionLength(1)))) km"
        let hours: String? = switch pick.openStatus {
        case "open": pick.closesAt.map { "Open until \($0)" } ?? "Open"
        case "closed": "Closed"
        default: nil
        }
        return [kind, PricePresentation.approximateSpendLabel(for: pick.priceLevel), distance, hours]
            .compactMap { $0 }
            .joined(separator: " · ")
    }

    private func fitSymbol(_ fit: PickFit) -> String {
        switch fit {
        case .strong: "flame.fill"
        case .good: "hand.thumbsup.fill"
        case .wildcard: "dice.fill"
        }
    }

    // MARK: - Action bar

    /// Going is the accept: "Let's go" records it and opens Maps. Another one / Not this sit
    /// beside it as icon buttons.
    private func actionBar(for pick: RecommendationResponse.Recommendation) -> some View {
        HStack(spacing: 12) {
            MakanPrimaryButton(title: Copy.resultGo) {
                openInMaps(pick)
            }

            barButton("arrow.triangle.2.circlepath", label: "Another one") {
                rerollTaps += 1
                exitEdge = .leading
                withAnimation(Motion.standard) { startReroll() }
            }

            barButton("hand.thumbsdown", label: "Not this") {
                rejectTaps += 1
                if let id = viewModel.currentPick?.id {
                    PlacePreferencesStore.shared.exclude(id)
                }
                exitEdge = .bottom
                withAnimation(Motion.standard) { startReroll() }
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 12)
        .padding(.bottom, 8)
        .background(Color.nasiCream)
        .overlay(alignment: .top) {
            Color.hairline.frame(height: 1)
        }
    }

    private func barButton(_ symbol: String, label: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: symbol)
                .font(.title3.weight(.semibold))
                .foregroundStyle(Color.kicap)
                .frame(width: 56, height: 56)
                .background(Color.surface, in: Circle())
                .overlay(Circle().strokeBorder(Color.hairline, lineWidth: 1))
        }
        .buttonStyle(PressCompressStyle())
        .accessibilityLabel(label)
    }

    // MARK: - Photo

    /// Full-bleed, under the nav bar. Settles from a slight zoom as it reveals.
    private func heroPhoto(for pick: RecommendationResponse.Recommendation) -> some View {
        ZStack(alignment: .bottomTrailing) {
            Group {
                if pick.photos.isEmpty {
                    Image(systemName: categoryIcon(for: pick.foodCategory))
                        .font(.largeTitle)
                        .imageScale(.large)
                        .foregroundStyle(Color.kunyit)
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                        .background(Color.surface)
                } else {
                    TabView(selection: $photoPage) {
                        ForEach(Array(pick.photos.enumerated()), id: \.offset) { index, photo in
                            Color.surface
                                .overlay {
                                    AsyncImage(url: URL(string: photo.url)) { phase in
                                        if case .success(let image) = phase {
                                            image.resizable().scaledToFill()
                                        } else {
                                            Image(systemName: categoryIcon(for: pick.foodCategory))
                                                .font(.largeTitle)
                                                .foregroundStyle(Color.kunyit)
                                        }
                                    }
                                }
                                .clipped()
                                .tag(index)
                        }
                    }
                    .tabViewStyle(.page(indexDisplayMode: pick.photos.count > 1 ? .automatic : .never))
                }
            }
            .scaleEffect(revealed || reduceMotion ? 1 : 1.06)
            .animation(revealed ? Motion.standard : nil, value: revealed)

            if let rating = pick.rating {
                Label(rating.formatted(.number.precision(.fractionLength(1))), systemImage: "star.fill")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Color.kicap)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(Color.surface, in: Capsule())
                    .padding(16)
            }
        }
        .frame(height: 340)
        .frame(maxWidth: .infinity)
        .clipped()
        // The app is light-only, so the status bar is dark text — a cream fade (not a dark scrim)
        // keeps it and the back button legible over dark photos.
        .overlay(alignment: .top) {
            LinearGradient(colors: [Color.nasiCream.opacity(0.75), .clear], startPoint: .top, endPoint: .bottom)
                .frame(height: 120)
                .allowsHitTesting(false)
        }
    }

    // MARK: - Reason chips

    @ViewBuilder
    private var reasonChips: some View {
        let chips = currentReasonChips()
        if !chips.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                Text("Why this?")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)

                HStack(spacing: 8) {
                    ForEach(Array(chips.enumerated()), id: \.offset) { index, chip in
                        Text(chip)
                            .font(.footnote)
                            .foregroundStyle(Color.kicap)
                            .padding(.horizontal, 10)
                            .padding(.vertical, 6)
                            .background(Color.surface, in: Capsule())
                            .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
                            .opacity(index < revealedReasonCount ? 1 : 0)
                            .offset(x: index < revealedReasonCount ? 0 : -6)
                            .animation(Motion.quick, value: revealedReasonCount)
                    }
                }
            }
        }
    }

    /// Reveals reason chips one at a time rather than all together — small enough to feel
    /// intentional (this restaurant was matched, not just returned), not a real delay.
    /// Server reasons when this is a Makan Brain pick, else the v1 input-echo chips.
    private var reasonCount: Int {
        let server = viewModel.currentPick?.reasons?.count ?? 0
        return server > 0 ? server : currentReasonChips().count
    }

    private func revealReasonChips() async {
        revealedReasonCount = 0
        let count = reasonCount
        for index in 0..<count {
            try? await Task.sleep(for: .milliseconds(90))
            guard !Task.isCancelled else { return }
            revealedReasonCount = index + 1
        }
    }

    private func currentReasonChips() -> [String] {
        var chips: [String] = []

        switch viewModel.cravingSelection {
        case .tag(let tag):
            if let mood = SoloViewModel.moodOptions.first(where: { $0.tag == tag }) {
                chips.append(mood.label)
            }
        case .custom(let text):
            let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
            if !trimmed.isEmpty { chips.append(trimmed) }
        case .anything, nil:
            break
        }

        if let tier = viewModel.budgetMax,
           let budget = SoloViewModel.budgetOptions.first(where: { $0.tier == tier }) {
            chips.append(budget.amount)
        } else {
            chips.append(Copy.anythingLabel)
        }

        if let distance = SoloViewModel.distanceOptions.first(where: { $0.km == viewModel.maxDistanceKm }) {
            chips.append(distance.label)
        }

        return chips
    }

    // MARK: - Reviews

    @ViewBuilder
    private var menuNudge: some View {
        Button {
            showingAddMenu = true
        } label: {
            HStack {
                Text("Know the menu? Add it")
                    .font(.subheadline)
                    .foregroundStyle(Color.kicap)
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }
            .padding(16)
            .panel()
        }
    }

    private func menuSection(for pick: RecommendationResponse.Recommendation) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Potential menu")
                .font(.footnote.weight(.semibold))
                .foregroundStyle(Color.kicapSecondary)

            PlaceMenuSection(items: pick.menuItems)

            Text("Shared by the MakanApa community. It may not be complete or up to date.")
                .font(.makanBody(10))
                .foregroundStyle(Color.kicapSecondary)
        }
        .padding(16)
        .panel()
    }

    private func reviewsSection(for pick: RecommendationResponse.Recommendation) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 4) {
                Text("Reviews from Google Maps · ordered by relevance")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)
                Image(systemName: "info.circle")
                    .font(.caption2)
                    .foregroundStyle(.secondary)
            }

            ForEach(Array(pick.reviews.enumerated()), id: \.offset) { _, review in
                reviewRow(review)
            }

            if let placeUrl = pick.placeGoogleMapsUrl, let url = URL(string: placeUrl) {
                Link(destination: url) {
                    HStack {
                        Text("View all reviews on Google Maps")
                            .font(.makanBody(12))
                            .foregroundStyle(Color.kicap)
                        Spacer()
                        Image(systemName: "chevron.right")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                }
            }
        }
        .padding(16)
        .panel()
    }

    @ViewBuilder
    private func reviewRow(_ review: RecommendationResponse.Review) -> some View {
        HStack(alignment: .top, spacing: 10) {
            AsyncImage(url: review.authorPhotoUrl.flatMap(URL.init)) { phase in
                if case .success(let image) = phase {
                    image.resizable().scaledToFill()
                } else {
                    Image(systemName: "person.crop.circle.fill")
                        .resizable()
                        .foregroundStyle(Color.hairline)
                }
            }
            .frame(width: 32, height: 32)
            .clipShape(Circle())

            VStack(alignment: .leading, spacing: 4) {
                if let rating = review.rating {
                    Text(String(repeating: "★", count: Int(rating.rounded())))
                        .font(.caption)
                        .foregroundStyle(Color.kunyit)
                }

                Text("\"\(truncated(review.text))\"")
                    .font(.makanBody(13))
                    .italic()
                    .foregroundStyle(Color.kicap)

                Text("\(review.authorName)\(review.relativePublishTime.map { " · \($0)" } ?? "")")
                    .font(.makanBody(11))
                    .foregroundStyle(.secondary)
            }

            Spacer()

            Menu {
                if let url = review.googleMapsUrl.flatMap(URL.init) {
                    Link("View on Google Maps", destination: url)
                }
                if let url = review.flagContentUrl.flatMap(URL.init) {
                    Link("Report", destination: url)
                }
            } label: {
                Image(systemName: "ellipsis")
                    .foregroundStyle(.secondary)
                    .padding(6)
            }
        }
    }

    private func truncated(_ text: String, limit: Int = 90) -> String {
        guard text.count > limit else { return text }
        return String(text.prefix(limit)).trimmingCharacters(in: .whitespaces) + "…"
    }

    // MARK: - Category labels

    private func categoryIcon(for foodCategory: String?) -> String {
        switch foodCategory {
        case "burger", "chicken", "sandwich", "fast_food": return "takeoutbag.and.cup.and.straw.fill"
        case "cafe", "breakfast": return "cup.and.saucer.fill"
        case "dessert", "bakery": return "birthday.cake.fill"
        case "drinks": return "wineglass.fill"
        default: return "fork.knife.circle.fill"
        }
    }

    /// Singular display label for the name-block subtitle — distinct from the plural
    /// headline treatment ("BURGERS.") which doesn't fit inline subtitle text.
    private func categorySubtitleLabel(_ foodCategory: String?) -> String? {
        switch foodCategory {
        case "burger": return "Burger"
        case "chicken": return "Chicken"
        case "pizza": return "Pizza"
        case "sandwich": return "Sandwich"
        case "ramen": return "Ramen"
        case "sushi": return "Sushi"
        case "seafood": return "Seafood"
        case "steak": return "Steak"
        case "bbq": return "BBQ"
        case "bakery": return "Bakery"
        case "dessert": return "Dessert"
        case "cafe": return "Cafe"
        case "drinks": return "Drinks"
        case "breakfast": return "Breakfast"
        case "fast_food": return "Fast Food"
        default: return nil
        }
    }

    // MARK: - Reveal sequence

    private func runRevealSequence() async {
        revealed = false
        revealedReasonCount = 0
        guard viewModel.currentPick != nil else { return }

        if reduceMotion {
            showTrace = false
            revealed = true
            revealedReasonCount = reasonCount
            revealTick += 1
            return
        }

        // Real thinking trace first (only on a fresh decision — reroll/tune results carry none).
        // Skipped when the request itself was slow: the loading screen already made them wait,
        // and replaying "thinking" after the answer exists just stacks a second delay on top.
        if let trace = viewModel.currentPick?.thinkingTrace, !trace.isEmpty,
           viewModel.lastDecisionLatency < Self.traceReplayLatencyLimit {
            showTrace = true
            try? await Task.sleep(for: ThinkingTraceView.duration(for: trace))
            guard !Task.isCancelled else { return }
            withAnimation(.easeOut(duration: 0.2)) { showTrace = false }
        }

        revealed = true
        revealTick += 1
        // Reasons tick in once the details have landed.
        try? await Task.sleep(for: .milliseconds(300))
        guard !Task.isCancelled else { return }
        await revealReasonChips()
    }

    // MARK: - Reroll

    /// Same visual language as the first search (PreferenceLoadingView): animated mascot,
    /// rounded display headline, one quiet Cancel — rerolling shouldn't look like a different app.
    private var rerollingContent: some View {
        VStack(spacing: 24) {
            AnimatedMakanMascot()
            VStack(spacing: 10) {
                Text(Copy.rerollHeadline)
                    .font(.system(size: 30, weight: .bold, design: .rounded))
                    .tracking(-0.8)
                    .foregroundStyle(Color.kicap)
                    .accessibilityAddTraits(.isHeader)
                Text("\(Copy.rerollLine1). \(Copy.rerollLine2).")
                    .font(.subheadline)
                    .foregroundStyle(Color.kicap.opacity(0.65))
            }
            .multilineTextAlignment(.center)
            HStack(spacing: 10) {
                ProgressView().tint(Color.pandan).accessibilityHidden(true)
                Text(Copy.rerollLine3)
                    .font(.footnote)
                    .foregroundStyle(Color.kicap.opacity(0.65))
            }
            .accessibilityElement(children: .combine)
            Button(action: cancelReroll) {
                Text("Cancel")
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                    .frame(minHeight: 44)
            }
        }
        .padding(.top, 24)
        .padding(.horizontal, 32)
    }

    private func startReroll() {
        isRerolling = true
        rerollTask = Task {
            let start = ContinuousClock.now
            await viewModel.reroll()
            let elapsed = ContinuousClock.now - start
            if elapsed < Self.minimumRerollDuration {
                try? await Task.sleep(for: Self.minimumRerollDuration - elapsed)
            }
            guard !Task.isCancelled else { return }
            // .task(id: viewModel.currentPick?.id) already fired while isRerolling was still
            // true, so its own photoPage reset was skipped — reset here instead, otherwise a
            // leftover page index from the old restaurant's photo count can point past the
            // new restaurant's (e.g. "4/1").
            photoPage = 0
            isRerolling = false
            await runRevealSequence()
        }
    }

    private func cancelReroll() {
        rerollTask?.cancel()
        rerollTask = nil
        isRerolling = false
    }

    // MARK: - No result

    /// "Try again" with the same filters just returns the same nothing — offer the two things
    /// that actually change the outcome: a wider search, or different preferences.
    private var noResultContent: some View {
        VStack(spacing: 16) {
            MascotView(mood: .sad, size: 72)
            VStack(spacing: 8) {
                Text(Copy.noResultHeadline)
                    .font(.makanDisplay(24))
                    .foregroundStyle(Color.kicap)
                    .multilineTextAlignment(.center)
                Text("Nothing matched within \(viewModel.maxDistanceKm.formatted()) km. Go a bit further, or loosen the filters.")
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
            if let wider = viewModel.widerDistanceKm {
                MakanPrimaryButton(title: "Search within \(wider.formatted()) km") {
                    Task { await viewModel.searchWider() }
                }
                .padding(.horizontal)
            }
            Button {
                router.pop()
            } label: {
                Text("Change preferences")
                    .font(.makanBody(15))
                    .foregroundStyle(Color.sambalRed)
                    .frame(minHeight: 44)
            }
        }
    }

    // MARK: - Error

    @ViewBuilder
    private func errorContent(for error: APIError) -> some View {
        let copy = errorCopy(for: error)

        MascotView(mood: .sad, size: 72)

        VStack(spacing: 8) {
            Text(copy.headline)
                .font(.makanDisplay(28))
                .foregroundStyle(Color.kicap)
            Text(copy.detail)
                .font(.makanBody(14))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }

        MakanPrimaryButton(title: Copy.tryAgain) {
            Task { await viewModel.retry() }
        }
        .padding(.horizontal)
        .padding(.top, 8)

        Button {
            router.popToRoot()
        } label: {
            Text(Copy.backHome)
                .font(.makanBody(15))
                .foregroundStyle(.secondary)
        }
    }

    private func errorCopy(for error: APIError) -> (headline: String, detail: String) {
        error.userFacingCopy
    }

    // MARK: - Makan Brain actions

    private func openWhatIf() {
        viewModel.logInteraction("reasons_expanded")
        whatIfEntries = []
        whatIfLoading = true
        showingWhatIf = true
        Task {
            whatIfEntries = await viewModel.whatIf()
            whatIfLoading = false
        }
    }

    private func tune(_ direction: TuneDirection) {
        isTuning = true
        Task {
            let moved = await viewModel.tune(direction)
            isTuning = false
            if moved {
                UINotificationFeedbackGenerator().notificationOccurred(.success)
            } else {
                UINotificationFeedbackGenerator().notificationOccurred(.warning)
            }
        }
    }

    // MARK: - Helpers

    private func walkingMinutes(for distanceKm: Double) -> Int {
        max(1, Int((distanceKm * 12).rounded()))
    }

    private func openInMaps(_ recommendation: RecommendationResponse.Recommendation) {
        viewModel.logInteraction("directions_opened")
        Task {
            await viewModel.acceptCurrentPick()
        }
        afterAccept()
        let coordinate = CLLocationCoordinate2D(latitude: recommendation.latitude, longitude: recommendation.longitude)
        let destination = MapDestination(
            coordinate: coordinate,
            name: recommendation.name,
            googleMapsURL: recommendation.placeGoogleMapsUrl.flatMap(URL.init(string:))
        )
        PreferredMapsLauncher.open(destination: destination, provider: MapProviderPreference.current)
    }
}

/// Staggered entrance: fade + 10pt rise, delayed per piece. Hiding is instant so a reroll resets
/// cleanly. Reduce Motion keeps the fade only.
private struct Reveal: ViewModifier {
    let isShown: Bool
    let delay: Double
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func body(content: Content) -> some View {
        content
            .opacity(isShown ? 1 : 0)
            .offset(y: isShown || reduceMotion ? 0 : 10)
            .animation(isShown ? (reduceMotion ? .easeOut(duration: 0.2) : Motion.standard.delay(delay)) : nil, value: isShown)
    }
}

private extension View {
    func reveal(_ isShown: Bool, delay: Double) -> some View {
        modifier(Reveal(isShown: isShown, delay: delay))
    }

    /// Secondary sections under the header: one surface, one border, one radius.
    func panel() -> some View {
        frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.surface, in: .card)
            .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
    }
}
