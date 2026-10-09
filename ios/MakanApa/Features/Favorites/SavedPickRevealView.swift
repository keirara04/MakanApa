import SwiftUI
import CoreLocation

/// "Pick from my saved" — the user's saved places as a deck of cards: fanned face-up so they see
/// these are *their* places, flipped and riffle-shuffled while Makan Brain picks, then the top
/// card is dealt face-up as the winner. The request runs from the first frame; the shuffle
/// always gets at least two riffles so a fast answer still reads as a shuffle. Faces are hidden
/// during the shuffle, so the dealt card can show any saved place — not just the six in the deck.
struct SavedPickRevealView: View {
    let places: [SavedPlace]
    let origin: CLLocationCoordinate2D
    /// Winner revealed and held — the caller pushes ResultView.
    let onDealt: () -> Void
    let onCancel: () -> Void

    @Environment(SoloViewModel.self) private var soloViewModel
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private enum Phase { case fanned, stacked, dealt, failed }

    @State private var deck: [SavedPlace]
    @State private var entered = false
    @State private var phase: Phase = .fanned
    @State private var isSplit = false
    /// Draw order: the last index is the top card — the one that gets dealt.
    @State private var order: [Int]
    @State private var tilts: [Double]
    @State private var faceShown = false
    @State private var isPicked = false

    /// The deck is dealt before the first frame so the fan-in has something to animate.
    init(places: [SavedPlace], origin: CLLocationCoordinate2D, onDealt: @escaping () -> Void, onCancel: @escaping () -> Void) {
        self.places = places
        self.origin = origin
        self.onDealt = onDealt
        self.onCancel = onCancel
        let deck = Array(places.shuffled().prefix(Self.deckSize))
        _deck = State(initialValue: deck)
        _order = State(initialValue: Array(deck.indices))
        _tilts = State(initialValue: deck.map { _ in Double.random(in: -3...3) })
    }

    private static let deckSize = 6
    private static let minimumRiffles = 2
    private static let riffleHalf: Duration = .milliseconds(175)

    var body: some View {
        VStack(spacing: 32) {
            Text(phase == .dealt ? Copy.savedPickDealt : Copy.savedPickShuffling)
                .font(.makanDisplay(22))
                .foregroundStyle(Color.kicap)
                .contentTransition(.opacity)
                .animation(Motion.quick, value: phase)
                .accessibilityAddTraits(.isHeader)

            Group {
                if reduceMotion {
                    stillDeck
                } else {
                    ZStack {
                        ForEach(deck.indices, id: \.self) { index in
                            card(at: index)
                        }
                    }
                }
            }
            .frame(height: 340)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(phase == .dealt ? winnerName : Copy.savedPickShuffling)

            footer
        }
        .padding(.horizontal, 24)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream)
        .sensoryFeedback(.success, trigger: faceShown) { _, shown in shown }
        .sensoryFeedback(.error, trigger: phase) { _, new in new == .failed }
        .task { await run() }
    }

    // MARK: - Cards

    @ViewBuilder
    private func card(at index: Int) -> some View {
        let isTop = order.last == index
        let depth = order.firstIndex(of: index) ?? 0
        let showsWinner = isTop && phase == .dealt

        SavedPlaceCardFace(
            name: showsWinner ? winnerName : deck[index].name,
            rating: showsWinner ? soloViewModel.currentPick?.rating : deck[index].rating,
            priceLevel: showsWinner ? soloViewModel.currentPick?.priceLevel : deck[index].priceLevel,
            isWinner: showsWinner
        )
        .modifier(CardFlip(angle: isFaceUp(isTop: isTop) ? 0 : 180, back: SavedPlaceCardBack()))
        .rotationEffect(.degrees(rotation(index: index)))
        .offset(offset(index: index, depth: depth, isTop: isTop))
        .scaleEffect(isTop && phase == .dealt ? 1.05 : 1)
        .opacity(cardOpacity(isTop: isTop))
        .zIndex(Double(depth))
        .animation(entranceAnimation(index: index), value: entered)
    }

    /// Reduce Motion: one face-down card that cross-fades to the winner — nothing moves.
    private var stillDeck: some View {
        ZStack {
            if faceShown {
                SavedPlaceCardFace(
                    name: winnerName, rating: soloViewModel.currentPick?.rating,
                    priceLevel: soloViewModel.currentPick?.priceLevel, isWinner: true
                )
                .transition(.opacity)
            } else {
                SavedPlaceCardBack()
                    .transition(.opacity)
            }
        }
        .opacity(entered ? (phase == .failed ? 0.25 : 1) : 0)
        .animation(.easeInOut(duration: 0.2), value: entered)
    }

    private func isFaceUp(isTop: Bool) -> Bool {
        switch phase {
        case .fanned: return true
        case .stacked, .failed: return false
        case .dealt: return isTop && faceShown
        }
    }

    private var center: Double { Double(deck.count - 1) / 2 }

    private func rotation(index: Int) -> Double {
        guard entered else { return 0 }
        switch phase {
        case .fanned: return (Double(index) - center) * 9
        case .stacked: return isSplit ? (index.isMultiple(of: 2) ? -4 : 4) : (tilts[safe: index] ?? 0)
        case .dealt, .failed: return 0
        }
    }

    private func offset(index: Int, depth: Int, isTop: Bool) -> CGSize {
        guard entered else { return CGSize(width: 0, height: 24) }
        switch phase {
        case .fanned:
            let spread = Double(index) - center
            return CGSize(width: spread * 30, height: abs(spread) * 8)
        case .stacked:
            // Stack thickness, plus the riffle's two halves sliding apart.
            let lift = -Double(depth) * 1.5
            guard isSplit else { return CGSize(width: 0, height: lift) }
            return CGSize(width: index.isMultiple(of: 2) ? -70 : 70, height: lift)
        case .dealt:
            return isTop ? CGSize(width: 0, height: -40) : CGSize(width: 0, height: 30 - Double(depth) * 1.5)
        case .failed:
            return CGSize(width: (Double(index) - center) * 40, height: 30)
        }
    }

    private func cardOpacity(isTop: Bool) -> Double {
        guard entered else { return 0 }
        switch phase {
        case .fanned, .stacked: return 1
        case .dealt: return isTop ? 1 : 0.35
        case .failed: return 0.25
        }
    }

    /// Cards fan in one after another (40 ms stagger); every later move is driven by run().
    private func entranceAnimation(index: Int) -> Animation {
        Motion.standard.delay(Double(index) * 0.04)
    }

    private var winnerName: String { soloViewModel.currentPick?.name ?? "" }

    // MARK: - Footer

    @ViewBuilder
    private var footer: some View {
        if phase == .failed {
            VStack(spacing: 16) {
                Text(failureMessage)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                Button(Copy.back, action: onCancel)
                    .font(.makanBody(15).weight(.semibold))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 28)
                    .frame(minHeight: 44)
                    .background(Color.sambalRed, in: Capsule())
                    .buttonStyle(PressCompressStyle())
            }
            .transition(.opacity.combined(with: .scale(scale: 0.95)))
        } else {
            Button(Copy.cancel, action: onCancel)
                .font(.makanBody(13))
                .foregroundStyle(Color.kicapSecondary)
                .frame(minHeight: 44)
                .opacity(phase == .dealt ? 0 : 1)
        }
    }

    private var failureMessage: String {
        switch soloViewModel.apiError {
        case .none: return Copy.savedPickNoneAvailable
        case .rateLimited: return Copy.savedPickRateLimited
        default: return Copy.savedPickFailed
        }
    }

    // MARK: - Sequence

    private func run() async {
        async let request: Void = pick()

        entered = true
        if reduceMotion {
            phase = .stacked
        } else {
            try? await Task.sleep(for: .milliseconds(650))
            withAnimation(Motion.standard) { phase = .stacked }
        }
        try? await Task.sleep(for: .milliseconds(350))

        var riffles = 0
        while !Task.isCancelled, riffles < Self.minimumRiffles || !isPicked {
            if reduceMotion {
                try? await Task.sleep(for: .milliseconds(150))
            } else {
                await riffle()
            }
            riffles += 1
        }
        await request
        guard !Task.isCancelled else { return }

        guard soloViewModel.currentPick != nil else {
            withAnimation(Motion.standard) { phase = .failed }
            return
        }

        withAnimation(reduceMotion ? .easeInOut(duration: 0.2) : Motion.playful) { phase = .dealt }
        try? await Task.sleep(for: .milliseconds(reduceMotion ? 0 : 150))
        withAnimation(reduceMotion ? .easeInOut(duration: 0.25) : .easeOut(duration: 0.4)) { faceShown = true }
        try? await Task.sleep(for: .milliseconds(900))
        guard !Task.isCancelled else { return }
        onDealt()
    }

    private func pick() async {
        await soloViewModel.pickFromSaved(places, origin: origin)
        isPicked = true
    }

    /// One riffle: the deck splits into two halves, then falls back together in a new order.
    private func riffle() async {
        withAnimation(.easeOut(duration: 0.17)) { isSplit = true }
        try? await Task.sleep(for: Self.riffleHalf)
        withAnimation(.easeOut(duration: 0.17)) {
            isSplit = false
            order.shuffle()
            tilts = deck.map { _ in Double.random(in: -4...4) }
        }
        try? await Task.sleep(for: Self.riffleHalf)
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
    let rating: Double?
    let priceLevel: Int?
    let isWinner: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Image(systemName: "heart.fill")
                .font(.subheadline)
                .foregroundStyle(Color.sambalRed)
            Spacer(minLength: 0)
            Text(name)
                .font(.makanDisplay(20))
                .foregroundStyle(Color.kicap)
                .lineLimit(3)
                .minimumScaleFactor(0.8)
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
        .padding(18)
        .frame(width: CardSize.width, height: CardSize.height, alignment: .leading)
        .background(Color.surface, in: .card)
        .overlay(RoundedRectangle.card.strokeBorder(isWinner ? Color.sambalRed : Color.hairline, lineWidth: isWinner ? 2 : 1))
        .shadow(color: .black.opacity(0.12), radius: 10, y: 6)
    }
}

private struct SavedPlaceCardBack: View {
    var body: some View {
        Image("NasiWave")
            .resizable()
            .scaledToFit()
            .frame(width: CardSize.width * 0.5)
            .frame(width: CardSize.width, height: CardSize.height)
            .background(Color.sambalRed, in: .card)
            .overlay(RoundedRectangle.card.inset(by: 8).strokeBorder(.white.opacity(0.35), lineWidth: 1))
            .shadow(color: .black.opacity(0.12), radius: 10, y: 6)
            .accessibilityLabel(Copy.savedPickCardBackLabel)
    }
}

private extension Array {
    subscript(safe index: Int) -> Element? { indices.contains(index) ? self[index] : nil }
}
