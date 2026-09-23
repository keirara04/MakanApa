import SwiftUI
import MapKit
import UIKit

struct ResultView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var viewModel
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var showIntro = false
    @State private var showMascot = false
    @State private var showHeadline = false
    @State private var showInfo = false
    @State private var showCTA = false
    @State private var isRerolling = false
    @State private var rerollTask: Task<Void, Never>?
    @State private var photoPage = 0
    @State private var exitEdge: Edge = .leading
    @State private var acceptSettle = false
    @State private var revealedReasonCount = 0
    @State private var showNotificationPriming = false
    @State private var showingAddMenu = false
    @State private var showTrace = false
    @State private var showingWhatIf = false
    @State private var whatIfEntries: [WhatIfEntry] = []
    @State private var whatIfLoading = false
    @State private var isTuning = false

    private static let minimumRerollDuration: Duration = .milliseconds(700)
    private static let traceReplayLatencyLimit: Duration = .seconds(2)

    var body: some View {
        VStack(spacing: 20) {
            MakanApaTopBar(onBack: { router.pop() })

            Spacer().frame(height: 20)

            if isRerolling {
                rerollingContent
            } else if let error = viewModel.apiError {
                errorContent(for: error)
            } else if let pick = viewModel.currentPick {
                resultContent(for: pick)
            } else {
                noResultContent
            }

            Spacer()
        }
        .padding()
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
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
        if !NotificationPrimingState.shared.hasSeenPriming {
            showNotificationPriming = true
        }
    }

    // MARK: - Result

    @ViewBuilder
    private func resultContent(for pick: RecommendationResponse.Recommendation) -> some View {
        ScrollView {
            VStack(spacing: 20) {
                if showTrace, let trace = pick.thinkingTrace, !trace.isEmpty {
                    ThinkingTraceView(lines: trace)
                        .padding(.horizontal, 24)
                        .transition(.opacity)
                } else {
                    Text(pick.fatigue == true ? "Okay lah, enough choosing 😭" : Copy.resultIntro)
                        .font(.makanBody(15))
                        .foregroundStyle(.secondary)
                        .opacity(showIntro ? 1 : 0)
                        .animation(.easeOut(duration: 0.2), value: showIntro)
                }

                MascotView(mood: .celebrate, size: 64)
                    .opacity(showMascot ? 1 : 0)
                    .animation(reduceMotion ? .easeOut(duration: 0.2) : .spring(response: 0.35, dampingFraction: 0.6), value: showMascot)

                VStack(spacing: 8) {
                    Text(pick.name)
                        .font(.makanDisplay(36))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.center)
                        .scaleEffect(showHeadline ? 1 : 0.85)
                        .opacity(showHeadline ? 1 : 0)
                        .animation(reduceMotion ? .easeOut(duration: 0.2) : .spring(response: 0.4, dampingFraction: 0.7), value: showHeadline)

                    Text(Copy.resultThatsIt)
                        .font(.makanBody(16))
                        .foregroundStyle(.secondary)
                        .opacity(showHeadline ? 1 : 0)

                    if let fit = pick.fit {
                        FitBadge(fit: fit)
                            .opacity(showHeadline ? 1 : 0)
                    }
                }

                VStack(spacing: 16) {
                    resultCard(for: pick)

                    nameBlock(for: pick)

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
                                .font(.makanBody(12))
                                .foregroundStyle(.secondary)
                            WhyNotChips { reason, detail in viewModel.sendWhyNot(reason, detail: detail) }
                        }
                        .padding(.vertical, 4)
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
                            .font(.makanBody(10))
                            .foregroundStyle(Color.kicap.opacity(0.4))
                    }
                }
                .padding(.horizontal, 8)
                .offset(y: showInfo ? 0 : 12)
                .opacity(showInfo ? 1 : 0)
                .animation(.easeOut(duration: 0.25), value: showInfo)

                VStack(spacing: 14) {
                    MakanPrimaryButton(title: Copy.jomMakan) {
                        openInMaps(pick)
                    }
                    .scaleEffect(acceptSettle ? 1.0 : 1.03)
                    .animation(Motion.playful, value: acceptSettle)

                    feedbackRow

                    if let adjustment = viewModel.searchWider {
                        SearchWiderBanner(message: viewModel.searchWiderMessage ?? "Nothing better nearby. Search a bit wider?", adjustment: adjustment) {
                            Task { await viewModel.acceptSearchWider() }
                        }
                    } else if viewModel.canTune {
                        TuneRow(used: viewModel.tunesUsed) { direction in tune(direction) }
                            .disabled(isTuning)
                            .opacity(isTuning ? 0.5 : 1)
                    }
                }
                .padding(.horizontal)
                .padding(.top, 8)
                .offset(y: showCTA ? 0 : 16)
                .opacity(showCTA ? 1 : 0)
                .animation(.easeOut(duration: 0.25), value: showCTA)
            }
        }
        .transition(.asymmetric(
            insertion: .opacity,
            removal: .move(edge: exitEdge).combined(with: .opacity)
        ))
    }

    // MARK: - Accept / reroll / reject

    private var feedbackRow: some View {
        HStack(spacing: 28) {
            feedbackButton(emoji: "👍", label: "Works for me") {
                UINotificationFeedbackGenerator().notificationOccurred(.success)
                acceptSettle = false
                withAnimation(Motion.playful) { acceptSettle = true }
                Task { await viewModel.acceptCurrentPick() }
                afterAccept()
            }
            feedbackButton(emoji: "🔄", label: "Another one") {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                exitEdge = .leading
                withAnimation(Motion.standard) { startReroll() }
            }
            feedbackButton(emoji: "👎", label: "Not this") {
                UIImpactFeedbackGenerator(style: .medium).impactOccurred()
                if let id = viewModel.currentPick?.id {
                    PlacePreferencesStore.shared.exclude(id)
                }
                exitEdge = .bottom
                withAnimation(Motion.standard) { startReroll() }
            }
        }
    }

    private func feedbackButton(emoji: String, label: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            VStack(spacing: 4) {
                Text(emoji).font(.system(size: 22))
                Text(label)
                    .font(.makanBody(11))
                    .foregroundStyle(.secondary)
            }
        }
        .buttonStyle(PressCompressStyle())
    }

    // MARK: - Photo card

    @ViewBuilder
    private func resultCard(for pick: RecommendationResponse.Recommendation) -> some View {
        ZStack(alignment: .topTrailing) {
            if pick.photos.isEmpty {
                VStack {
                    Spacer()
                    Image(systemName: categoryIcon(for: pick.foodCategory))
                        .font(.system(size: 56))
                        .foregroundStyle(Color.kunyit)
                    Spacer()
                }
                .frame(maxWidth: .infinity)
                .frame(height: 160)
            } else {
                TabView(selection: $photoPage) {
                    ForEach(Array(pick.photos.enumerated()), id: \.offset) { index, photo in
                        AsyncImage(url: URL(string: photo.url)) { phase in
                            switch phase {
                            case .success(let image):
                                image.resizable().scaledToFill()
                            default:
                                ZStack {
                                    Color.kicap.opacity(0.06)
                                    Image(systemName: categoryIcon(for: pick.foodCategory))
                                        .font(.system(size: 48))
                                        .foregroundStyle(Color.kunyit)
                                }
                            }
                        }
                        .tag(index)
                    }
                }
                .tabViewStyle(.page(indexDisplayMode: .never))
                .frame(height: 160)
                .clipShape(RoundedRectangle(cornerRadius: 24))

                if pick.photos.count > 1 {
                    Text("\(photoPage + 1)/\(pick.photos.count)")
                        .font(.makanBody(10))
                        .padding(.horizontal, 8)
                        .padding(.vertical, 3)
                        .background(.black.opacity(0.5))
                        .foregroundStyle(.white)
                        .clipShape(Capsule())
                        .padding(10)
                        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .bottomLeading)
                }
            }

            if let rating = pick.rating {
                HStack(spacing: 4) {
                    Image(systemName: "star.fill")
                        .foregroundStyle(Color.kunyit)
                    Text("\(rating, specifier: "%.1f")")
                        .foregroundStyle(Color.kicap)
                }
                .font(.makanBody(12))
                .padding(.horizontal, 10)
                .padding(.vertical, 5)
                .background(.white)
                .clipShape(Capsule())
                .padding(12)
            }
        }
        .background(Color.kicap.opacity(0.06))
        .clipShape(RoundedRectangle(cornerRadius: 24))
        .shadow(color: Color.kicap.opacity(0.07), radius: 8, y: 4)

        HStack(spacing: 10) {
            if let spend = PricePresentation.approximateSpendLabel(for: pick.priceLevel) {
                detailChip(icon: "dollarsign.circle", text: spend)
            }
            detailChip(icon: "figure.walk", text: "~\(walkingMinutes(for: pick.distanceKm)) min (\(String(format: "%.1f", pick.distanceKm)) km)")
        }
    }

    private func detailChip(icon: String, text: String) -> some View {
        HStack(spacing: 4) {
            Image(systemName: icon)
            Text(text)
        }
        .font(.makanBody(12))
        .foregroundStyle(Color.kicap.opacity(0.8))
        .padding(.horizontal, 10)
        .padding(.vertical, 6)
        .background(Color.kicap.opacity(0.06))
        .clipShape(Capsule())
    }

    // MARK: - Name block

    @ViewBuilder
    private func nameBlock(for pick: RecommendationResponse.Recommendation) -> some View {
        VStack(spacing: 4) {
            let subtitle = [categorySubtitleLabel(pick.foodCategory), pick.cuisines.first?.capitalized]
                .compactMap { $0 }
                .joined(separator: " · ")
            if !subtitle.isEmpty {
                Text(subtitle)
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
            }

            openStatusRow(pick.openStatus, closesAt: pick.closesAt)

            if let halal = pick.halal {
                HalalBadge(display: halal.display)
                    .padding(.top, 2)
            }
        }
    }

    @ViewBuilder
    private func openStatusRow(_ status: String, closesAt: String?) -> some View {
        let (color, text): (Color, String) = switch status {
        case "open": (Color.pandan, "Open")
        case "closed": (Color.kicap.opacity(0.4), "Closed")
        default: (Color.kicap.opacity(0.25), "Hours unknown")
        }
        HStack(spacing: 6) {
            Circle().fill(color).frame(width: 7, height: 7)
            if status == "open", let closesAt {
                Text("\(text) · Closes \(closesAt)")
            } else {
                Text(text)
            }
        }
        .font(.makanBody(12))
        .foregroundStyle(.secondary)
    }

    // MARK: - Reason chips

    @ViewBuilder
    private var reasonChips: some View {
        let chips = currentReasonChips()
        if !chips.isEmpty {
            VStack(alignment: .leading, spacing: 8) {
                Text("Why this?")
                    .font(.makanBody(11))
                    .foregroundStyle(.secondary)

                HStack(spacing: 8) {
                    ForEach(Array(chips.enumerated()), id: \.offset) { index, chip in
                        Text(chip)
                            .font(.makanBody(11))
                            .foregroundStyle(Color.kicap)
                            .padding(.horizontal, 10)
                            .padding(.vertical, 5)
                            .background(Color.kicap.opacity(0.06))
                            .clipShape(Capsule())
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
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap)
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.system(size: 11))
                    .foregroundStyle(.secondary)
            }
            .padding(14)
            .background(Color.kicap.opacity(0.04))
            .clipShape(RoundedRectangle(cornerRadius: 18))
        }
    }

    private func menuSection(for pick: RecommendationResponse.Recommendation) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Potential menu")
                .font(.makanBody(10))
                .foregroundStyle(.secondary)

            PlaceMenuSection(items: pick.menuItems)

            Text("Shared by the MakanApa community — may not be complete or up to date.")
                .font(.makanBody(10))
                .foregroundStyle(Color.kicap.opacity(0.4))
        }
        .padding(14)
        .background(Color.kicap.opacity(0.04))
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }

    private func reviewsSection(for pick: RecommendationResponse.Recommendation) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 4) {
                Text("Reviews from Google Maps · ordered by relevance")
                    .font(.makanBody(10))
                    .foregroundStyle(.secondary)
                Image(systemName: "info.circle")
                    .font(.system(size: 10))
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
                            .font(.system(size: 11))
                            .foregroundStyle(.secondary)
                    }
                }
            }
        }
        .padding(14)
        .background(Color.kicap.opacity(0.04))
        .clipShape(RoundedRectangle(cornerRadius: 18))
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
                        .foregroundStyle(Color.kicap.opacity(0.3))
                }
            }
            .frame(width: 32, height: 32)
            .clipShape(Circle())

            VStack(alignment: .leading, spacing: 4) {
                if let rating = review.rating {
                    Text(String(repeating: "★", count: Int(rating.rounded())))
                        .font(.system(size: 11))
                        .foregroundStyle(Color.kunyit)
                }

                Text("\"\(truncated(review.text))\"")
                    .font(.makanBody(13))
                    .italic()
                    .foregroundStyle(Color.kicap.opacity(0.85))

                Text("— \(review.authorName)\(review.relativePublishTime.map { " · \($0)" } ?? "")")
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
        showIntro = false; showMascot = false; showHeadline = false; showInfo = false; showCTA = false
        revealedReasonCount = 0
        guard viewModel.currentPick != nil else { return }

        if reduceMotion {
            showTrace = false
            showIntro = true; showMascot = true; showHeadline = true; showInfo = true; showCTA = true
            revealedReasonCount = reasonCount
            UINotificationFeedbackGenerator().notificationOccurred(.success)
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

        showIntro = true
        try? await Task.sleep(for: .milliseconds(100))
        guard !Task.isCancelled else { return }
        showMascot = true
        try? await Task.sleep(for: .milliseconds(120))
        guard !Task.isCancelled else { return }
        showHeadline = true
        UINotificationFeedbackGenerator().notificationOccurred(.success)
        try? await Task.sleep(for: .milliseconds(150))
        guard !Task.isCancelled else { return }
        showInfo = true
        await revealReasonChips()
        guard !Task.isCancelled else { return }
        showCTA = true
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
