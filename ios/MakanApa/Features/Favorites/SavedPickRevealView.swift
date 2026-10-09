import SwiftUI
import CoreLocation
import MapKit

/// What a saved-places pick starts from. Built by Home's card and Saved's button alike.
struct SavedPickLaunch: Identifiable {
    let id = UUID()
    let places: [SavedPlace]
    let origin: CLLocationCoordinate2D
    /// False when `origin` is only the saved places' centroid — then nothing claims "from you".
    let originIsUser: Bool

    /// Nil below two saved places — one place isn't a decision. Without location the centroid of
    /// the saved places stands in for the user, so distance just counts for less.
    static func make(places: [SavedPlace], location: LocationState) -> SavedPickLaunch? {
        guard places.count >= 2 else { return nil }
        if case let .authorized(coordinate) = location {
            return SavedPickLaunch(places: places, origin: coordinate, originIsUser: true)
        }
        let count = Double(places.count)
        return SavedPickLaunch(places: places, origin: CLLocationCoordinate2D(
            latitude: places.map(\.latitude).reduce(0, +) / count,
            longitude: places.map(\.longitude).reduce(0, +) / count
        ), originIsUser: false)
    }
}

extension View {
    /// Immersive, so a full-screen cover with its own Close — and it never hands off to
    /// ResultView: the pick and why it won are the whole answer here.
    func savedPickCover(_ launch: Binding<SavedPickLaunch?>) -> some View {
        fullScreenCover(item: launch) { launch in
            SavedPickRevealView(places: launch.places, origin: launch.origin, originIsUser: launch.originIsUser)
        }
    }
}

/// "Pick from my saved", staged like a card trick: the user's places fan out face-up on a dark
/// table, flip, and get riffle-shuffled under a warm spotlight while Makan Brain picks. Then a
/// beat — "And the pick is…" — the rest of the deck sinks away, the top card rises and turns
/// over slowly, and a "Why this one" panel explains the pick. Faces are hidden during the
/// shuffle, so the dealt card can be any saved place, not just the six in the deck.
struct SavedPickRevealView: View {
    let places: [SavedPlace]
    let origin: CLLocationCoordinate2D
    let originIsUser: Bool

    @Environment(SoloViewModel.self) private var soloViewModel
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.dismiss) private var dismiss

    private enum Phase { case boxed, fanned, stacked, revealing, revealed, failed, exhausted }

    @State private var deck: [SavedPlace]
    /// Draw order: the last index is the top card — the one that gets dealt.
    @State private var order: [Int]
    @State private var tilts: [Double]
    @State private var entered = false
    @State private var phase: Phase = .boxed
    // The blind-box intro: the deck arrives sealed, gets a shake, pops open, and the cards climb
    // out — buying the deck's map snapshots time to render before anyone sees a face.
    @State private var box = CardBoxMotion()
    @State private var cardsOut = false
    @State private var boxLands = 0
    @State private var boxShakes = 0
    @State private var boxPops = 0
    @State private var isSplit = false
    @State private var riffleSnaps = 0
    @State private var faceShown = false
    @State private var sheen = false
    @State private var showReasons = false
    @State private var isAnswered = false
    /// Bumped by "Shuffle again" — keys the sequence task, so closing the cover cancels it.
    @State private var round = 0
    /// The winner's street, rendered once per reveal — a still image so the card can flip and
    /// tilt without a live map view glitching inside the 3D transform.
    @State private var map: MapSnapshot?
    /// Every deck card's own street map, keyed by place id — so the fan isn't six blank faces.
    @State private var deckMaps: [Int: MapSnapshot] = [:]

    private static let deckSize = 6
    private static let minimumRiffles = 2

    /// The deck is dealt before the first frame so the fan-in has something to animate.
    init(places: [SavedPlace], origin: CLLocationCoordinate2D, originIsUser: Bool) {
        self.places = places
        self.origin = origin
        self.originIsUser = originIsUser
        let deck = Array(places.shuffled().prefix(Self.deckSize))
        _deck = State(initialValue: deck)
        _order = State(initialValue: Array(deck.indices))
        _tilts = State(initialValue: deck.map { _ in Double.random(in: -3...3) })
    }

    var body: some View {
        GeometryReader { proxy in
            ScrollView {
                VStack(spacing: 24) {
                    title
                    deckArea
                    if phase == .revealed {
                        whyPanel
                        actions
                    } else if phase == .failed || phase == .exhausted {
                        failure
                    } else {
                        deckCaption
                    }
                }
                .padding(.horizontal, 24)
                .padding(.top, 64)
                .padding(.bottom, 32)
                .frame(maxWidth: 480)
                .frame(maxWidth: .infinity)
                // Centered on the stage while shuffling; grows and scrolls once the reasons arrive.
                .frame(minHeight: proxy.size.height)
            }
            .scrollBounceBehavior(.basedOnSize)
            .scrollIndicators(.hidden)
        }
        .background { stage }
        .overlay(alignment: .topLeading) { closeButton }
        .statusBarHidden()
        .sensoryFeedback(.impact(weight: .light), trigger: boxLands)
        .sensoryFeedback(.impact(weight: .light), trigger: boxShakes)
        .sensoryFeedback(.impact(weight: .medium), trigger: boxPops)
        .sensoryFeedback(.impact(flexibility: .soft, intensity: 0.5), trigger: riffleSnaps)
        .sensoryFeedback(.success, trigger: faceShown) { _, shown in shown }
        .sensoryFeedback(.error, trigger: phase) { _, new in new == .failed || new == .exhausted }
        .task(id: round) {
            if round == 0 { await firstDeal() } else { await dealAgain() }
        }
    }

    // MARK: - Stage

    /// Near-black table under a warm kunyit spotlight — the brand's turmeric doing the job of a
    /// stage light, brightest at the moment of the reveal.
    private var stage: some View {
        ZStack {
            Color.stage
            RadialGradient(
                colors: [Color.kunyit.opacity(spotlight), .clear],
                center: UnitPoint(x: 0.5, y: 0.36), startRadius: 8, endRadius: 380
            )
            .animation(.easeInOut(duration: 0.8), value: phase)
        }
        .ignoresSafeArea()
    }

    private var spotlight: Double {
        switch phase {
        case .boxed: box.glow ? 0.22 : 0.14
        case .fanned, .stacked: 0.12
        case .revealing: 0.3
        case .revealed: 0.18
        case .failed, .exhausted: 0.05
        }
    }

    private var title: some View {
        Text(phase == .revealing ? Copy.savedPickRevealing : Copy.savedPickShuffling)
            .font(.makanDisplay(22))
            .foregroundStyle(.white)
            .contentTransition(.opacity)
            .opacity(entered && phase != .boxed && phase != .revealed && phase != .failed && phase != .exhausted ? 1 : 0)
            .animation(.easeInOut(duration: 0.4), value: phase)
            .animation(.easeOut(duration: 0.4), value: entered)
            .frame(minHeight: 32)
            .accessibilityAddTraits(.isHeader)
    }

    private var deckCaption: some View {
        let names = deck.prefix(2).map(\.name)
        return Text(Copy.savedPickInDeck(Array(names), more: places.count - names.count))
            .font(.makanBody(13))
            .foregroundStyle(.white.opacity(0.6))
            .multilineTextAlignment(.center)
            .lineLimit(2)
            .opacity(entered ? 1 : 0)
            .animation(.easeOut(duration: 0.4).delay(0.3), value: entered)
    }

    private var closeButton: some View {
        Button(action: { dismiss() }) {
            Image(systemName: "xmark")
                .font(.body.weight(.semibold))
                .foregroundStyle(.white)
                .frame(width: 44, height: 44)
                .background(.white.opacity(0.12), in: Circle())
        }
        .padding(.leading, 16)
        .padding(.top, 8)
        .accessibilityLabel(Copy.close)
    }

    // MARK: - Deck

    private var deckArea: some View {
        Group {
            if reduceMotion {
                stillDeck
            } else {
                ZStack {
                    if phase == .boxed {
                        CardBoxShadow(motion: box).zIndex(-2)
                        CardBoxInside(motion: box).zIndex(-1)
                    }
                    ForEach(deck.indices, id: \.self) { index in
                        card(at: index)
                    }
                    if phase == .boxed {
                        CardBoxFront(motion: box, title: Copy.savedPickTitle).zIndex(50)
                        // Past upright the lid is behind the cards climbing out of the box.
                        CardBoxLid(motion: box).zIndex(box.lidAngle > 90 ? -0.5 : 51)
                    }
                }
                // Looking down at the table while the cards are shuffled; level for the reveal.
                .rotation3DEffect(.degrees(phase == .stacked ? 12 : 0), axis: (x: 1, y: 0, z: 0), perspective: 0.6)
                .scaleEffect(phase == .stacked ? 0.94 : 1)
                .animation(.easeInOut(duration: 0.6), value: phase)
            }
        }
        .frame(height: phase == .revealed ? 250 : 340)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(phase == .revealed ? (soloViewModel.currentPick?.name ?? "") : Copy.savedPickShuffling)
    }

    @ViewBuilder
    private func card(at index: Int) -> some View {
        let depth = order.firstIndex(of: index) ?? 0
        let isTop = depth == deck.count - 1
        let isLeftHalf = depth < deck.count / 2
        let showsWinner = isTop && (phase == .revealing || phase == .revealed)
        let pick = soloViewModel.currentPick

        SavedPlaceCardFace(
            name: showsWinner ? (pick?.name ?? "") : deck[index].name,
            category: showsWinner ? pick?.foodCategory : nil,
            rating: showsWinner ? pick?.rating : deck[index].rating,
            priceLevel: showsWinner ? pick?.priceLevel : deck[index].priceLevel,
            isWinner: showsWinner,
            sheen: showsWinner && sheen,
            map: .slot(showsWinner ? (map ?? pick.flatMap { deckMaps[$0.id] }) : deckMaps[deck[index].id]),
            travel: showsWinner ? travelLabel : travelText(to: deck[index])
        )
        .modifier(CardFlip(angle: isFaceUp(isTop: isTop) ? 0 : 180, back: SavedPlaceCardBack()))
        // The riffle: each half bends toward the middle like cards under a thumb.
        .rotation3DEffect(.degrees(isSplit && phase == .stacked ? (isLeftHalf ? 26 : -26) : 0), axis: (x: 0, y: 1, z: 0), perspective: 0.5)
        .rotationEffect(.degrees(rotation(index: index, isLeftHalf: isLeftHalf)))
        .offset(offset(index: index, depth: depth, isTop: isTop, isLeftHalf: isLeftHalf))
        .scaleEffect(scale(isTop: isTop))
        .opacity(opacity(isTop: isTop))
        .zIndex(Double(depth))
        .animation(Motion.standard.delay(Double(index) * 0.05), value: entered)
        // Out of the box top card first, each one a beat behind the last.
        .animation(.spring(response: 0.55, dampingFraction: 0.78).delay(Double(deck.count - 1 - depth) * 0.08), value: cardsOut)
        // Split together, then fall back in one card at a time, bottom first — the cascade.
        .animation(
            isSplit
                ? .easeOut(duration: 0.24).delay(Double(depth) * 0.015)
                : .spring(response: 0.3, dampingFraction: 0.82).delay(Double(depth) * 0.04),
            value: isSplit
        )
    }

    private func isFaceUp(isTop: Bool) -> Bool {
        switch phase {
        case .fanned: true
        case .boxed, .stacked, .failed, .exhausted: false
        case .revealing, .revealed: isTop && faceShown
        }
    }

    private var center: Double { Double(deck.count - 1) / 2 }

    private func rotation(index: Int, isLeftHalf: Bool) -> Double {
        guard entered else { return 0 }
        switch phase {
        case .boxed: return cardsOut ? (tilts[safe: index] ?? 0) : 0
        case .fanned: return (Double(index) - center) * 9
        case .stacked: return isSplit ? (isLeftHalf ? -9 : 9) : (tilts[safe: index] ?? 0)
        case .revealing, .revealed: return 0
        case .failed, .exhausted: return (Double(index) - center) * 4
        }
    }

    private func offset(index: Int, depth: Int, isTop: Bool, isLeftHalf: Bool) -> CGSize {
        guard entered else { return CGSize(width: 0, height: 40) }
        switch phase {
        case .boxed:
            // Inside, the cards ride along with the box; out, they stand above its mouth.
            return cardsOut
                ? CGSize(width: CardBoxMotion.cardsInside.width, height: -70 - Double(depth) * 5)
                : CGSize(width: CardBoxMotion.cardsInside.width, height: CardBoxMotion.cardsInside.height + box.dropY + box.hop)
        case .fanned:
            let spread = Double(index) - center
            return CGSize(width: spread * 30, height: abs(spread) * 8)
        case .stacked:
            let thickness = -Double(depth) * 1.5
            guard isSplit else { return CGSize(width: 0, height: thickness) }
            return CGSize(width: isLeftHalf ? -84 : 84, height: thickness - 12)
        case .revealing:
            return isTop ? CGSize(width: 0, height: -12) : CGSize(width: 0, height: 140)
        case .revealed:
            return isTop ? .zero : CGSize(width: 0, height: 140)
        case .failed, .exhausted:
            return CGSize(width: (Double(index) - center) * 36, height: 30)
        }
    }

    private func scale(isTop: Bool) -> Double {
        switch phase {
        case .boxed: cardsOut ? 0.9 : 0.74
        case .revealing: isTop ? 1.1 : 0.9
        case .revealed: isTop ? 0.86 : 0.9
        default: 1
        }
    }

    private func opacity(isTop: Bool) -> Double {
        guard entered else { return 0 }
        switch phase {
        case .boxed: return 1
        case .fanned, .stacked: return 1
        case .revealing, .revealed: return isTop ? 1 : 0
        case .failed, .exhausted: return 0.2
        }
    }

    /// Reduce Motion: one card that cross-fades from its back to the winner — nothing travels.
    private var stillDeck: some View {
        ZStack {
            if faceShown, let pick = soloViewModel.currentPick {
                SavedPlaceCardFace(
                    name: pick.name, category: pick.foodCategory, rating: pick.rating,
                    priceLevel: pick.priceLevel, isWinner: true, sheen: false,
                    map: .slot(map), travel: travelLabel
                )
                .transition(.opacity)
            } else {
                SavedPlaceCardBack()
                    .transition(.opacity)
            }
        }
        .scaleEffect(phase == .revealed ? 0.86 : 1)
        .opacity(entered ? (phase == .failed || phase == .exhausted ? 0.2 : 1) : 0)
        .animation(.easeInOut(duration: 0.25), value: entered)
    }

    // MARK: - Why this one

    private struct WhyLine: Hashable {
        let symbol: String
        let text: String
    }

    /// Brain's own reasons when it ran (already led by "One of your saved places"), else the
    /// server's headline; then what only this phone knows — when it was saved — and the plain
    /// facts. Server emoji icons are never drawn; reasons map to SF Symbols by family.
    private var whyLines: [WhyLine] {
        guard let pick = soloViewModel.currentPick else { return [] }
        var lines: [WhyLine] = []

        // Always first, even when the server didn't lead with it (rerolls, the v1 picker).
        lines.append(WhyLine(symbol: "heart.fill", text: Copy.savedPickReasonSaved))
        if let reasons = pick.reasons, !reasons.isEmpty {
            lines += reasons.map { WhyLine(symbol: symbol(forFamily: $0.family, text: $0.text), text: $0.text) }
        } else {
            if !pick.headline.isEmpty {
                lines.append(WhyLine(symbol: "text.quote", text: pick.headline))
            }
        }
        if let saved = places.first(where: { $0.id == pick.id }) {
            lines.append(WhyLine(
                symbol: "clock.arrow.circlepath",
                text: Copy.savedPickReasonSavedAgo(saved.savedAt.formatted(.relative(presentation: .named)))
            ))
        }
        if pick.openStatus == "open" {
            lines.append(WhyLine(symbol: "door.left.hand.open", text: Copy.savedPickReasonOpen))
        }
        if originIsUser {
            lines.append(WhyLine(symbol: "location.fill", text: Copy.savedPickReasonDistance(Self.distanceText(pick.distanceKm))))
        }

        var seen = Set<String>()
        return Array(lines.filter { seen.insert($0.text).inserted }.prefix(4))
    }

    private static func distanceText(_ km: Double) -> String {
        Measurement(value: km, unit: UnitLength.kilometers)
            .formatted(.measurement(width: .abbreviated, usage: .road, numberFormatStyle: .number.precision(.fractionLength(0...1))))
    }

    /// Walking pace (~5 km/h) up to 2 km, plain distance beyond; nothing without real location.
    private var travelLabel: String? {
        guard let km = soloViewModel.currentPick?.distanceKm else { return nil }
        return travelText(km: km)
    }

    private func travelText(to place: SavedPlace) -> String? {
        travelText(km: distanceKm(to: CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude)))
    }

    private func travelText(km: Double) -> String? {
        guard originIsUser else { return nil }
        guard km <= 2 else { return Copy.savedPickAway(Self.distanceText(km)) }
        return Copy.savedPickWalk(minutes: max(1, Int((km / 5 * 60).rounded())))
    }

    private func distanceKm(to coordinate: CLLocationCoordinate2D) -> Double {
        CLLocation(latitude: origin.latitude, longitude: origin.longitude)
            .distance(from: CLLocation(latitude: coordinate.latitude, longitude: coordinate.longitude)) / 1000
    }

    private func symbol(forFamily family: String, text: String) -> String {
        if text == Copy.savedPickReasonSaved { return "heart.fill" }
        switch family {
        case "edge": return "star.fill"
        case "moment": return "clock.fill"
        default: return "checkmark.seal.fill"
        }
    }

    private var whyPanel: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text(Copy.savedPickWhyTitle)
                .font(.makanDisplay(17))
                .foregroundStyle(.white)
                .accessibilityAddTraits(.isHeader)

            ForEach(Array(whyLines.enumerated()), id: \.element) { index, line in
                Label {
                    Text(line.text)
                        .fixedSize(horizontal: false, vertical: true)
                } icon: {
                    Image(systemName: line.symbol)
                        .foregroundStyle(Color.kunyit)
                }
                .font(.makanBody(15))
                .foregroundStyle(.white.opacity(0.9))
                .opacity(showReasons ? 1 : 0)
                .offset(y: showReasons || reduceMotion ? 0 : 10)
                .animation(Motion.standard.delay(reduceMotion ? 0 : Double(index) * 0.09), value: showReasons)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(20)
        .background(.white.opacity(0.07), in: .card)
        .overlay(RoundedRectangle.card.strokeBorder(.white.opacity(0.1), lineWidth: 1))
        .transition(.opacity)
    }

    private var actions: some View {
        VStack(spacing: 8) {
            Button(action: letsEat) {
                Text(Copy.resultGo)
                    .font(.makanBody(17).weight(.semibold))
                    .foregroundStyle(.white)
                    .frame(maxWidth: .infinity, minHeight: 52)
                    .background(Color.sambalRed, in: Capsule())
            }
            .buttonStyle(PressCompressStyle())

            Button {
                round += 1
            } label: {
                Label(Copy.savedPickShuffleAgain, systemImage: "shuffle")
                    .font(.makanBody(15).weight(.semibold))
                    .foregroundStyle(.white.opacity(0.85))
                    .frame(maxWidth: .infinity, minHeight: 44)
            }
        }
        .opacity(showReasons ? 1 : 0)
        .animation(Motion.standard.delay(reduceMotion ? 0 : 0.4), value: showReasons)
    }

    private var failure: some View {
        VStack(spacing: 16) {
            Text(failureMessage)
                .font(.makanBody(15))
                .foregroundStyle(.white)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
            Button(action: { dismiss() }) {
                Text(Copy.close)
                    .font(.makanBody(15).weight(.semibold))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 32)
                    .frame(minHeight: 44)
                    .background(Color.sambalRed, in: Capsule())
            }
            .buttonStyle(PressCompressStyle())
        }
        .transition(.opacity)
    }

    private var failureMessage: String {
        if phase == .exhausted { return Copy.savedPickNoMore }
        switch soloViewModel.apiError {
        case .none: return Copy.savedPickNoneAvailable
        case .rateLimited: return Copy.savedPickRateLimited
        default: return Copy.savedPickFailed
        }
    }

    // MARK: - Sequence

    private func firstDeal() async {
        isAnswered = false
        async let request: Void = pickFirst()
        loadDeckMaps()

        entered = true
        if reduceMotion {
            phase = .stacked
        } else {
            let start = ContinuousClock.now
            await openBox()
            withAnimation(Motion.standard) { phase = .fanned }
            // Hold the fan until the cards' maps are in (they fade in as they land), capped so a
            // slow render never stalls the show — the box has usually covered the wait already.
            try? await Task.sleep(for: .milliseconds(800))
            while deckMaps.count < deck.count, ContinuousClock.now - start < .milliseconds(3600), !Task.isCancelled {
                try? await Task.sleep(for: .milliseconds(100))
            }
            try? await Task.sleep(for: .milliseconds(300))
            phase = .stacked
            try? await Task.sleep(for: .milliseconds(450))
        }
        await shuffleUntilAnswered()
        await request
        await reveal(exhaustedWhenEmpty: false)
    }

    /// Drop and land, rock, pop the lid, cards climb out, box sinks away — about 2.6 s.
    private func openBox() async {
        withAnimation(.easeIn(duration: 0.38)) { box.dropY = 0 }
        try? await Task.sleep(for: .milliseconds(380))
        boxLands += 1
        withAnimation(.easeOut(duration: 0.07)) { box.squash = true }
        try? await Task.sleep(for: .milliseconds(70))
        withAnimation(.spring(response: 0.3, dampingFraction: 0.5)) { box.squash = false }
        try? await Task.sleep(for: .milliseconds(480))

        // Rocks on its base, each swing smaller — something inside wants out.
        boxShakes += 1
        for angle in [-6.0, 5.0, -3.5, 2.0, 0] {
            withAnimation(.easeInOut(duration: 0.09)) { box.rock = angle }
            try? await Task.sleep(for: .milliseconds(90))
        }
        try? await Task.sleep(for: .milliseconds(220))

        boxPops += 1
        withAnimation(.easeOut(duration: 0.12)) { box.hop = -10 }
        withAnimation(.spring(response: 0.6, dampingFraction: 0.62)) { box.lidAngle = 165 }
        withAnimation(.easeOut(duration: 0.5)) { box.glow = true }
        try? await Task.sleep(for: .milliseconds(120))
        withAnimation(.spring(response: 0.35, dampingFraction: 0.55)) { box.hop = 0 }
        try? await Task.sleep(for: .milliseconds(300))

        cardsOut = true
        try? await Task.sleep(for: .milliseconds(850))

        withAnimation(.easeIn(duration: 0.45)) { box.sunk = true }
        try? await Task.sleep(for: .milliseconds(250))
    }

    /// "Shuffle again": the winner turns back over, the deck gathers, and the stored pool gets
    /// rerolled server-side (the current pick is rejected, so it can't come straight back).
    private func dealAgain() async {
        isAnswered = false
        withAnimation(.easeOut(duration: 0.2)) { showReasons = false }
        sheen = false
        withAnimation(reduceMotion ? .easeInOut(duration: 0.2) : .easeInOut(duration: 0.45)) { faceShown = false }
        map = nil
        try? await Task.sleep(for: .milliseconds(reduceMotion ? 200 : 450))

        async let request: Void = rerollPick()
        withAnimation(.easeInOut(duration: 0.5)) { phase = .stacked }
        try? await Task.sleep(for: .milliseconds(500))
        await shuffleUntilAnswered()
        await request
        await reveal(exhaustedWhenEmpty: true)
    }

    private func loadDeckMaps() {
        let user = originIsUser ? origin : nil
        for place in deck {
            let coordinate = CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude)
            let km = distanceKm(to: coordinate)
            Task {
                guard let snapshot = await MapSnapshot.make(place: coordinate, user: user, distanceKm: km) else { return }
                withAnimation(.easeOut(duration: 0.3)) { deckMaps[place.id] = snapshot }
            }
        }
    }

    private func pickFirst() async {
        await soloViewModel.pickFromSaved(places, origin: origin)
        isAnswered = true
    }

    private func rerollPick() async {
        await soloViewModel.reroll()
        isAnswered = true
    }

    /// At least two riffles, so a fast answer still reads as a shuffle, not a flicker.
    private func shuffleUntilAnswered() async {
        var riffles = 0
        while !Task.isCancelled, riffles < Self.minimumRiffles || !isAnswered {
            if reduceMotion {
                try? await Task.sleep(for: .milliseconds(250))
            } else {
                await riffle()
            }
            riffles += 1
        }
    }

    /// Split (per-card implicit animations carry the stagger), then cascade back in a new order.
    private func riffle() async {
        isSplit = true
        try? await Task.sleep(for: .milliseconds(320))
        isSplit = false
        order.shuffle()
        tilts = deck.map { _ in Double.random(in: -4...4) }
        try? await Task.sleep(for: .milliseconds(170))
        riffleSnaps += 1
        try? await Task.sleep(for: .milliseconds(330))
    }

    private func reveal(exhaustedWhenEmpty: Bool) async {
        guard !Task.isCancelled else { return }
        // A failed reroll keeps the previous pick in place — never deal it a second time.
        guard soloViewModel.currentPick != nil, soloViewModel.apiError == nil else {
            let failed: Phase = exhaustedWhenEmpty && soloViewModel.apiError == nil ? .exhausted : .failed
            withAnimation(Motion.standard) { phase = failed }
            return
        }
        if let pick = soloViewModel.currentPick {
            // Renders during the reveal beat; fades onto the card whenever it's ready.
            let place = CLLocationCoordinate2D(latitude: pick.latitude, longitude: pick.longitude)
            let user = originIsUser ? origin : nil
            Task {
                let snapshot = await MapSnapshot.make(place: place, user: user, distanceKm: pick.distanceKm)
                withAnimation(.easeOut(duration: 0.3)) { map = snapshot }
            }
        }

        if reduceMotion {
            phase = .revealing
            withAnimation(.easeInOut(duration: 0.3)) { faceShown = true }
            try? await Task.sleep(for: .milliseconds(500))
        } else {
            // The beat: the deck sinks away and the top card rises into the light, still face down.
            withAnimation(.easeInOut(duration: 0.6)) { phase = .revealing }
            try? await Task.sleep(for: .milliseconds(900))
            // A slow turn, not a snap.
            withAnimation(.easeInOut(duration: 0.75)) { faceShown = true }
            try? await Task.sleep(for: .milliseconds(750))
            withAnimation(.easeInOut(duration: 0.9)) { sheen = true }
            try? await Task.sleep(for: .milliseconds(700))
        }
        guard !Task.isCancelled else { return }
        withAnimation(Motion.standard) { phase = .revealed }
        try? await Task.sleep(for: .milliseconds(150))
        showReasons = true
    }

    private func letsEat() {
        guard let pick = soloViewModel.currentPick else { return }
        Task { await soloViewModel.acceptCurrentPick() }
        let destination = MapDestination(
            coordinate: CLLocationCoordinate2D(latitude: pick.latitude, longitude: pick.longitude),
            name: pick.name,
            googleMapsURL: pick.placeGoogleMapsUrl.flatMap(URL.init(string:))
        )
        PreferredMapsLauncher.open(destination: destination, provider: MapProviderPreference.current)
        dismiss()
    }
}

// MARK: - Card pieces

/// Rotates around Y and swaps to `back` past 90° so the card reads as a real flip. The back is
/// mirrored so it isn't drawn backwards once the card is turned over.
private struct CardFlip<Back: View>: ViewModifier, Animatable {
    var angle: Double
    let back: Back

    nonisolated var animatableData: Double {
        get { angle }
        set { angle = newValue }
    }

    func body(content: Content) -> some View {
        ZStack {
            content.opacity(angle < 90 ? 1 : 0)
            back.scaleEffect(x: -1, y: 1).opacity(angle < 90 ? 0 : 1)
        }
        .rotation3DEffect(.degrees(angle), axis: (x: 0, y: 1, z: 0), perspective: 0.5)
    }
}

private enum CardSize {
    static let width: CGFloat = 200
    static let height: CGFloat = 280
}

private struct SavedPlaceCardFace: View {
    let name: String
    let category: String?
    let rating: Double?
    let priceLevel: Int?
    let isWinner: Bool
    /// One light sweep across the winner's face once it's turned over.
    let sheen: Bool
    var map: MapSlot = .none
    var travel: String? = nil

    enum MapSlot {
        case none
        /// The winner's map header — `nil` while the snapshot is still rendering.
        case slot(MapSnapshot?)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            if case let .slot(snapshot) = map {
                MapHeader(snapshot: snapshot)
            }
            details
        }
        .frame(width: CardSize.width, height: CardSize.height, alignment: .top)
        .background(Color.surface, in: .card)
        .overlay {
            LinearGradient(colors: [.clear, .white.opacity(0.6), .clear], startPoint: .leading, endPoint: .trailing)
                .frame(width: 70)
                .rotationEffect(.degrees(20))
                .offset(x: sheen ? CardSize.width : -CardSize.width)
                .opacity(isWinner ? 1 : 0)
                .allowsHitTesting(false)
        }
        .clipShape(.card)
        .overlay(RoundedRectangle.card.strokeBorder(isWinner ? Color.kunyit : Color.hairline, lineWidth: isWinner ? 2 : 1))
        .shadow(color: .black.opacity(isWinner ? 0.45 : 0.3), radius: isWinner ? 24 : 10, y: isWinner ? 14 : 6)
    }

    private var hasMap: Bool {
        if case .slot = map { return true }
        return false
    }

    private var details: some View {
        VStack(alignment: .leading, spacing: 8) {
            if !hasMap {
                Image(systemName: "heart.fill")
                    .font(.subheadline)
                    .foregroundStyle(Color.sambalRed)
            }
            if let travel {
                Label(travel, systemImage: "figure.walk")
                    .font(.makanBody(13).weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)
                    .monospacedDigit()
            }
            Spacer(minLength: 0)
            Text(name)
                .font(.makanDisplay(20))
                .foregroundStyle(Color.kicap)
                .lineLimit(hasMap ? 2 : 3)
                .minimumScaleFactor(0.75)
            if let category {
                Text(category)
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicapSecondary)
                    .lineLimit(1)
            }
            HStack(spacing: 6) {
                if let rating {
                    Label(rating.formatted(.number.precision(.fractionLength(1))), systemImage: "star.fill")
                        .foregroundStyle(Color.kunyit)
                }
                if let spend = PricePresentation.approximateSpendLabel(for: priceLevel) {
                    Text(spend)
                        .foregroundStyle(Color.kicapSecondary)
                }
            }
            .font(.makanBody(13))
            .monospacedDigit()
        }
        .padding(16)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
    }
}

/// A still map of the winner's street: the place's pin, plus the user's dot when they're close
/// enough to share the frame (within 1.5 km). Rendered in light style to match the card face.
struct MapSnapshot {
    let image: UIImage
    let pin: CGPoint
    let user: CGPoint?

    static let size = CGSize(width: CardSize.width, height: 116)

    static func make(place: CLLocationCoordinate2D, user: CLLocationCoordinate2D?, distanceKm: Double) async -> MapSnapshot? {
        let options = MKMapSnapshotter.Options()
        if let user, distanceKm <= 1.5 {
            let latitudeSpan = max(abs(place.latitude - user.latitude) * 2.2, 0.004)
            let longitudeSpan = max(abs(place.longitude - user.longitude) * 2.2, 0.004)
            options.region = MKCoordinateRegion(
                center: CLLocationCoordinate2D(latitude: (place.latitude + user.latitude) / 2, longitude: (place.longitude + user.longitude) / 2),
                span: MKCoordinateSpan(latitudeDelta: latitudeSpan, longitudeDelta: longitudeSpan)
            )
        } else {
            options.region = MKCoordinateRegion(center: place, latitudinalMeters: 450, longitudinalMeters: 450)
        }
        options.size = size
        options.traitCollection = UITraitCollection(userInterfaceStyle: .light)
        options.pointOfInterestFilter = .excludingAll

        return await withCheckedContinuation { continuation in
            MKMapSnapshotter(options: options).start(with: .main) { snapshot, _ in
                guard let snapshot else { return continuation.resume(returning: nil) }
                let userPoint = user.map { snapshot.point(for: $0) }
                let bounds = CGRect(origin: .zero, size: size).insetBy(dx: 6, dy: 6)
                continuation.resume(returning: MapSnapshot(
                    image: snapshot.image,
                    pin: snapshot.point(for: place),
                    user: userPoint.flatMap { bounds.contains($0) ? $0 : nil }
                ))
            }
        }
    }
}

private struct MapHeader: View {
    let snapshot: MapSnapshot?

    var body: some View {
        ZStack(alignment: .topLeading) {
            Color.hairline
            if let snapshot {
                Image(uiImage: snapshot.image)
                    .resizable()
                    .transition(.opacity)
                if let user = snapshot.user {
                    Circle()
                        .fill(Color(.systemBlue))
                        .frame(width: 12, height: 12)
                        .overlay(Circle().strokeBorder(.white, lineWidth: 2.5))
                        .shadow(color: .black.opacity(0.25), radius: 2, y: 1)
                        .position(user)
                }
                Image(systemName: "mappin.circle.fill")
                    .font(.system(size: 26))
                    .symbolRenderingMode(.palette)
                    .foregroundStyle(.white, Color.sambalRed)
                    .shadow(color: .black.opacity(0.3), radius: 3, y: 2)
                    .position(snapshot.pin)
            } else {
                Image(systemName: "map")
                    .font(.title2)
                    .foregroundStyle(Color.kicapSecondary)
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            }
        }
        .frame(width: MapSnapshot.size.width, height: MapSnapshot.size.height)
        .clipped()
        .accessibilityLabel(Copy.savedPickMapLabel)
    }
}

private struct SavedPlaceCardBack: View {
    var body: some View {
        Image("Avatar_nasi")
            .resizable()
            .scaledToFit()
            .frame(width: CardSize.width * 0.62)
            .frame(width: CardSize.width, height: CardSize.height)
            .background(Color.sambalRed, in: .card)
            .overlay(RoundedRectangle.card.inset(by: 8).strokeBorder(.white.opacity(0.4), lineWidth: 1.5))
            .shadow(color: .black.opacity(0.3), radius: 10, y: 6)
            .accessibilityLabel(Copy.savedPickCardBackLabel)
    }
}

private extension Array {
    subscript(safe index: Int) -> Element? { indices.contains(index) ? self[index] : nil }
}

// MARK: - Card box (blind-box intro)

/// Everything the box does, shared by its layers so they move as one object. The cards are drawn
/// *between* the inside and the front, so the box is split into layers on one shared canvas.
struct CardBoxMotion {
    var dropY: CGFloat = -340
    var squash = false
    var rock: Double = 0
    var hop: CGFloat = 0
    var lidAngle: Double = 0
    var glow = false
    var sunk = false

    /// Where the box sits in the deck area, and where the cards ride inside it.
    static let centerY: CGFloat = 60
    static let cardsInside = CGSize(width: -12, height: centerY + 46)
}

/// The box drawn in three-quarter view on a fixed canvas: front face, right side face, top.
/// Light comes from the top left, like the stage spotlight.
private enum CardBoxGeometry {
    static let canvas = CGSize(width: 264, height: 310)
    static let front = CGRect(x: 18, y: 96, width: 204, height: 210)
    /// How far the back edge sits up and to the right of the front edge.
    static let depth = CGSize(width: 34, height: -26)
    static let lidLip: CGFloat = 34

    static var side: [CGPoint] {
        [CGPoint(x: front.maxX, y: front.minY), CGPoint(x: front.maxX + depth.width, y: front.minY + depth.height),
         CGPoint(x: front.maxX + depth.width, y: front.maxY + depth.height), CGPoint(x: front.maxX, y: front.maxY)]
    }

    static var top: [CGPoint] {
        [CGPoint(x: front.minX, y: front.minY), CGPoint(x: front.minX + depth.width, y: front.minY + depth.height),
         CGPoint(x: front.maxX + depth.width, y: front.minY + depth.height), CGPoint(x: front.maxX, y: front.minY)]
    }
}

private struct Polygon: Shape {
    let points: [CGPoint]
    func path(in rect: CGRect) -> Path {
        Path { path in
            path.addLines(points)
            path.closeSubpath()
        }
    }
}

/// Moves a box layer: drop, landing squash, rocking on its base, the hop when the lid pops, and
/// sinking away at the end. Every layer gets the same one, on the same canvas, so they stay glued.
private struct CardBoxPlacement: ViewModifier {
    let motion: CardBoxMotion

    func body(content: Content) -> some View {
        content
            .frame(width: CardBoxGeometry.canvas.width, height: CardBoxGeometry.canvas.height, alignment: .topLeading)
            .scaleEffect(x: motion.squash ? 1.06 : 1, y: motion.squash ? 0.92 : 1, anchor: .bottom)
            .rotationEffect(.degrees(motion.rock), anchor: .bottom)
            .offset(y: motion.dropY + motion.hop + (motion.sunk ? 70 : 0))
            .scaleEffect(motion.sunk ? 0.9 : 1)
            .opacity(motion.sunk ? 0 : 1)
            .offset(y: CardBoxMotion.centerY)
            .allowsHitTesting(false)
            .accessibilityHidden(true)
    }
}

/// Contact shadow on the table — stays put while the box falls, firming up as it lands.
private struct CardBoxShadow: View {
    let motion: CardBoxMotion

    var body: some View {
        let landed = motion.dropY > -40
        Ellipse()
            .fill(.black.opacity(motion.sunk ? 0 : landed ? 0.6 : 0.2))
            .frame(width: 240, height: 26)
            .blur(radius: 10)
            .scaleEffect(landed ? 1 : 0.5)
            .offset(x: 6, y: CardBoxMotion.centerY + CardBoxGeometry.canvas.height / 2 - 6)
            .allowsHitTesting(false)
            .accessibilityHidden(true)
    }
}

/// The dark inside of the box, plus the light that spills out of it once the lid is open.
private struct CardBoxInside: View {
    let motion: CardBoxMotion

    var body: some View {
        ZStack(alignment: .topLeading) {
            // A column of warm light rising out of the open box.
            LinearGradient(colors: [Color.kunyit.opacity(0.55), .clear], startPoint: .bottom, endPoint: .top)
                .frame(width: 170, height: 230)
                .blur(radius: 14)
                .position(x: CardBoxGeometry.front.midX + 17, y: CardBoxGeometry.front.minY - 120)
                .opacity(motion.glow ? 1 : 0)
            Polygon(points: CardBoxGeometry.top)
                .fill(LinearGradient(colors: [Color(red: 0.16, green: 0.1, blue: 0.08), Color(red: 0.32, green: 0.2, blue: 0.14)],
                                     startPoint: .top, endPoint: .bottom))
            Polygon(points: CardBoxGeometry.top)
                .fill(RadialGradient(colors: [Color.kunyit.opacity(0.65), .clear], center: UnitPoint(x: 0.5, y: 0.28),
                                     startRadius: 2, endRadius: 110))
                .opacity(motion.glow ? 1 : 0)
        }
        .modifier(CardBoxPlacement(motion: motion))
    }
}

/// Front and side faces, with the Nasi mascot printed on the front.
private struct CardBoxFront: View {
    let motion: CardBoxMotion
    let title: String

    var body: some View {
        let front = CardBoxGeometry.front
        ZStack(alignment: .topLeading) {
            // Side face: same card stock, turned away from the light.
            Polygon(points: CardBoxGeometry.side)
                .fill(Color.surface)
                .overlay(Polygon(points: CardBoxGeometry.side)
                    .fill(LinearGradient(colors: [.black.opacity(0.18), .black.opacity(0.34)], startPoint: .top, endPoint: .bottom)))
            Polygon(points: [
                CGPoint(x: front.maxX, y: front.maxY - 30), CGPoint(x: front.maxX + 34, y: front.maxY - 56),
                CGPoint(x: front.maxX + 34, y: front.maxY - 44), CGPoint(x: front.maxX, y: front.maxY - 18),
            ])
            .fill(Color.sambalRed)
            .brightness(-0.18)

            // Front face.
            VStack(spacing: 8) {
                Spacer().frame(height: CardBoxGeometry.lidLip + 12)
                Image("Avatar_nasi")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 88)
                    .shadow(color: .black.opacity(0.12), radius: 3, y: 2)
                Text(title)
                    .font(.makanDisplay(14))
                    .foregroundStyle(Color.kicap)
                Spacer(minLength: 0)
                Color.sambalRed.frame(height: 12)
                    .padding(.bottom, 18)
            }
            .frame(width: front.width, height: front.height)
            .background(Color.surface)
            .overlay(LinearGradient(colors: [.white.opacity(0.35), .clear, .black.opacity(0.12)],
                                    startPoint: .topLeading, endPoint: .bottomTrailing))
            .overlay(alignment: .leading) {
                // Light catching the front-left edge.
                LinearGradient(colors: [.white.opacity(0.7), .white.opacity(0.1)], startPoint: .top, endPoint: .bottom)
                    .frame(width: 1.5)
            }
            .overlay(alignment: .trailing) {
                Color.black.opacity(0.18).frame(width: 1)
            }
            .offset(x: front.minX, y: front.minY)
        }
        .modifier(CardBoxPlacement(motion: motion))
    }
}

/// The red lid: top panel plus front and side lips, hinged on the back edge. Past upright you see
/// its darker inside.
private struct CardBoxLid: View {
    let motion: CardBoxMotion

    var body: some View {
        let front = CardBoxGeometry.front
        let depth = CardBoxGeometry.depth
        let lip = CardBoxGeometry.lidLip
        let size = CGSize(width: front.width + depth.width, height: -depth.height + lip)

        LidShape(outside: true, size: size)
            .modifier(LidHinge(angle: motion.lidAngle, inside: LidShape(outside: false, size: size)))
            .frame(width: size.width, height: size.height)
            .offset(x: front.minX, y: front.minY + depth.height)
            .modifier(CardBoxPlacement(motion: motion))
    }
}

private struct LidShape: View {
    let outside: Bool
    let size: CGSize

    var body: some View {
        let depth = CardBoxGeometry.depth
        let lip = CardBoxGeometry.lidLip
        let frontWidth = size.width - depth.width
        let top = -depth.height
        ZStack(alignment: .topLeading) {
            // Top panel — faces the light.
            Polygon(points: [CGPoint(x: 0, y: top), CGPoint(x: depth.width, y: 0), CGPoint(x: size.width, y: 0), CGPoint(x: frontWidth, y: top)])
                .fill(Color.sambalRed)
                .overlay(Polygon(points: [CGPoint(x: 0, y: top), CGPoint(x: depth.width, y: 0), CGPoint(x: size.width, y: 0), CGPoint(x: frontWidth, y: top)])
                    .fill(LinearGradient(colors: [.white.opacity(outside ? 0.3 : 0), .clear], startPoint: .topLeading, endPoint: .bottomTrailing)))
            // Side lip — in shadow.
            Polygon(points: [CGPoint(x: frontWidth, y: top), CGPoint(x: size.width, y: 0), CGPoint(x: size.width, y: lip), CGPoint(x: frontWidth, y: top + lip)])
                .fill(Color.sambalRed)
                .brightness(-0.22)
            // Front lip.
            Rectangle()
                .fill(Color.sambalRed)
                .overlay(LinearGradient(colors: [.white.opacity(outside ? 0.14 : 0), .black.opacity(0.1)], startPoint: .top, endPoint: .bottom))
                .overlay(alignment: .top) { Color.white.opacity(outside ? 0.55 : 0).frame(height: 1) }
                .frame(width: frontWidth, height: lip)
                .offset(y: top)
        }
        .brightness(outside ? 0 : -0.35)
        .frame(width: size.width, height: size.height, alignment: .topLeading)
    }
}

/// Swings the lid around its back edge (the top of its frame) and swaps to the inside past 90°.
private struct LidHinge<Inside: View>: ViewModifier, Animatable {
    var angle: Double
    let inside: Inside

    nonisolated var animatableData: Double {
        get { angle }
        set { angle = newValue }
    }

    func body(content: Content) -> some View {
        ZStack {
            content.opacity(angle < 90 ? 1 : 0)
            inside.scaleEffect(x: 1, y: -1).opacity(angle < 90 ? 0 : 1)
        }
        .rotation3DEffect(.degrees(angle), axis: (x: 1, y: 0, z: 0), anchor: .top, perspective: 0.45)
    }
}
