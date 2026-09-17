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

    private static let minimumRerollDuration: Duration = .milliseconds(700)

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
    }

    // MARK: - Result

    @ViewBuilder
    private func resultContent(for pick: RecommendationResponse.Recommendation) -> some View {
        ScrollView {
            VStack(spacing: 20) {
                Text(Copy.resultIntro)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)
                    .opacity(showIntro ? 1 : 0)
                    .animation(.easeOut(duration: 0.2), value: showIntro)

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
                }

                VStack(spacing: 16) {
                    resultCard(for: pick)

                    nameBlock(for: pick)

                    reasonChips

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
    private func revealReasonChips() async {
        revealedReasonCount = 0
        let count = currentReasonChips().count
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
            showIntro = true; showMascot = true; showHeadline = true; showInfo = true; showCTA = true
            revealedReasonCount = currentReasonChips().count
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            return
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

    private var rerollingContent: some View {
        VStack(spacing: 20) {
            MascotView(mood: .thinking, caption: Copy.rerollHeadline, size: 80)
            ThinkingChecklist(lines: [Copy.rerollLine1, Copy.rerollLine2, Copy.rerollLine3])
            Button(action: cancelReroll) {
                Text("Cancel")
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
            }
        }
        .padding(.top, 40)
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

    private var noResultContent: some View {
        VStack(spacing: 16) {
            MascotView(mood: .sad, size: 72)
            VStack(spacing: 8) {
                Text(Copy.noResultHeadline)
                    .font(.makanDisplay(24))
                    .foregroundStyle(Color.kicap)
                    .multilineTextAlignment(.center)
                Text(Copy.noResultDetail)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
            MakanPrimaryButton(title: Copy.tryAgain) {
                Task { await viewModel.retry() }
            }
            .padding(.horizontal)
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
        switch error {
        case .transport:
            return (Copy.connectionErrorHeadline, Copy.connectionErrorDetail)
        default:
            return (Copy.genericAPIErrorHeadline, Copy.genericAPIErrorDetail)
        }
    }

    // MARK: - Helpers

    private func walkingMinutes(for distanceKm: Double) -> Int {
        max(1, Int((distanceKm * 12).rounded()))
    }

    private func openInMaps(_ recommendation: RecommendationResponse.Recommendation) {
        Task {
            await viewModel.acceptCurrentPick()
        }
        let coordinate = CLLocationCoordinate2D(latitude: recommendation.latitude, longitude: recommendation.longitude)
        let placemark = MKPlacemark(coordinate: coordinate)
        let mapItem = MKMapItem(placemark: placemark)
        mapItem.name = recommendation.name
        mapItem.openInMaps()
    }
}
