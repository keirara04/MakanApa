import CoreLocation
import SwiftUI

/// Height is *derived* from state, never the other way round (plan correction 16) — a drag
/// only ever produces a transient visual offset on top of the current state's canonical height,
/// and always snaps back to one of these three on release.
enum NearbyPanelState: CaseIterable {
    case collapsed, medium, large

    func height(screenHeight: CGFloat) -> CGFloat {
        switch self {
        // Deliberately not tall enough to fit a full-width button underneath the summary row —
        // that would recreate the "too much bottom chrome" problem this panel replaced. The
        // Pick-one-lah CTA stays compact and inline at this height instead.
        case .collapsed: return 100
        case .medium: return 320
        case .large: return screenHeight * 0.82
        }
    }
}

/// "Default" (not "Recommended" — plan correction 19): this is just the backend's existing
/// order, with no real ranking score behind it yet.
enum NearbySortOption: String, CaseIterable, Identifiable {
    case defaultOrder = "Default"
    case rating = "Rating"
    case price = "Price"
    case distance = "Distance"

    var id: String { rawValue }
}

/// Custom draggable overlay — deliberately not a system `.sheet` (plan correction 1): this is
/// core map interface, not a modal task, and needs to sit above the tab bar without competing
/// with it. Nearby's "what's around here" interpretation layer: counts, top rated, community
/// finds, area personality, and (at `.large`) the full sortable list — one surface, driven
/// entirely by `NearbyViewModel`'s existing `areaSummary`/`places`/`browseCenter`, never a
/// second network request.
struct NearbyAreaPanel: View {
    let summary: AreaSummaryResponse?
    let places: [NearbyPlace]
    let browseCenter: CLLocationCoordinate2D?
    let onSelectPlace: (NearbyPlace) -> Void
    let onPickOneLah: () -> Void
    let isPicking: Bool
    let hasPlaces: Bool

    @Binding var state: NearbyPanelState
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var dragTranslation: CGFloat = 0
    @State private var sort: NearbySortOption = .defaultOrder
    @State private var listScrollOffset: CGFloat = 0

    private var screenHeight: CGFloat { UIScreen.main.bounds.height }

    private var listIsAtTop: Bool { listScrollOffset >= -1 }

    private var currentHeight: CGFloat {
        let base = state.height(screenHeight: screenHeight) + dragTranslation
        return min(max(base, NearbyPanelState.collapsed.height(screenHeight: screenHeight)), NearbyPanelState.large.height(screenHeight: screenHeight))
    }

    var body: some View {
        VStack(spacing: 0) {
            grabber
            content
        }
        .frame(maxWidth: .infinity)
        .frame(height: currentHeight, alignment: .top)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 26, style: .continuous))
        .shadow(color: .black.opacity(0.08), radius: 16, y: -2)
        .gesture(dragGesture)
    }

    private var grabber: some View {
        Capsule()
            .fill(Color.kicap.opacity(0.12))
            .frame(width: 28, height: 3)
            .padding(.vertical, 8)
    }

    @ViewBuilder
    private var content: some View {
        switch state {
        case .collapsed:
            collapsedRow.transition(.opacity)
        case .medium:
            mediumContent.transition(.opacity)
        case .large:
            largeContent.transition(.opacity)
        }
    }

    // One row: two-line "Around here / N places nearby" on the left, a compact inline CTA on
    // the right — never a full-width button underneath, which would force this state to grow
    // past ~100pt and start eating map space again.
    private var collapsedRow: some View {
        HStack(alignment: .center, spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text("Around here")
                    .font(.makanBody(15))
                    .fontWeight(.semibold)
                    .foregroundStyle(Color.kicap)
                HStack(spacing: 4) {
                    Text(collapsedSubtitle)
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                    // Deliberately small/secondary — a big chevron would compete with the CTA
                    // for attention right next to it.
                    Image(systemName: "chevron.up")
                        .font(.system(size: 9, weight: .semibold))
                        .foregroundStyle(.secondary.opacity(0.7))
                }
            }
            Spacer(minLength: 8)
            pickOneLahButton(compact: true)
        }
        .padding(.horizontal, 16)
        .contentShape(Rectangle())
    }

    // "Around here", never "Around <university>" — affiliation answers who your community is,
    // not where the map is currently pointed (plan correction 4).
    private var collapsedSubtitle: String {
        guard let summary else { return "" }
        return "\(summary.placeCount) places nearby"
    }

    /// One button, two sizes — `compact` for the collapsed row's inline CTA, full-width
    /// otherwise. The transition between them (collapsed → medium) is a crossfade via each
    /// state's `.transition(.opacity)` above, not a simultaneous-full-opacity swap.
    @ViewBuilder
    private func pickOneLahButton(compact: Bool) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .medium).impactOccurred()
            onPickOneLah()
        } label: {
            Text(isPicking ? "Nasi tengah fikir..." : "Pick one lah")
                .font(compact ? .makanBody(14) : .makanDisplay(16))
                .foregroundStyle(.white)
                .padding(.horizontal, compact ? 16 : 20)
                .padding(.vertical, compact ? 10 : 13)
                .frame(maxWidth: compact ? nil : .infinity)
                .background(Color.sambalRed)
                .clipShape(Capsule())
                .shadow(color: .black.opacity(0.15), radius: 6, y: 3)
        }
        .disabled(isPicking || !hasPlaces)
        .opacity(hasPlaces ? 1 : 0.5)
        .buttonStyle(PressCompressStyle())
    }

    // MARK: - Medium

    @ViewBuilder
    private var mediumContent: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 14) {
                if let summary, summary.placeCount > 0 {
                    countsRow(summary)
                    if !summary.personalityTags.isEmpty {
                        personalityTagsRow(summary.personalityTags)
                    }
                    if !summary.topRated.isEmpty {
                        topRatedSection(summary.topRated)
                    }
                    if !summary.communityFinds.isEmpty {
                        communityFindsSection(summary.communityFinds)
                    }
                    pickOneLahButton(compact: false)
                        .padding(.top, 4)
                } else {
                    emptyState
                }
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 16)
        }
    }

    private var emptyState: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("Nothing around here yet")
                .font(.makanBody(15))
                .foregroundStyle(Color.kicap)
            Text("Try moving the map or clearing a filter.")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
        }
    }

    private func countsRow(_ summary: AreaSummaryResponse) -> some View {
        HStack(spacing: 20) {
            countLabel("\(summary.placeCount)", "places")
            countLabel("\(summary.openNowCount)", "open now")
            countLabel("\(summary.budgetFriendlyCount)", "under RM20")
        }
    }

    private func countLabel(_ value: String, _ label: String) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(value)
                .font(.makanBody(16))
                .fontWeight(.semibold)
                .foregroundStyle(Color.kicap)
                .contentTransition(.numericText())
            Text(label)
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
        }
    }

    // Renders {key,label} data from the backend — the backend never sends emoji/prose (plan
    // correction 8), so this layer owns "how a personality tag actually looks."
    private func personalityTagsRow(_ tags: [AreaPersonalityTag]) -> some View {
        HStack(spacing: 8) {
            ForEach(tags) { tag in
                Text("\(personalityEmoji(for: tag.key)) \(tag.label)")
                    .capsuleTagStyle()
            }
        }
    }

    private func personalityEmoji(for key: String) -> String {
        switch key {
        case "budget_friendly": return "💸"
        case "category_heavy": return "🍛"
        default: return "✨"
        }
    }

    // "Top rated" — not "Trending" (plan correction 2): Community's feed already uses
    // "Trending" for a real accept-count signal; this is a much simpler in-viewport rating sort.
    private func topRatedSection(_ items: [NearbyPlace]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            sectionHeader("TOP RATED")
            ForEach(Array(items.enumerated()), id: \.element.id) { index, place in
                Button {
                    onSelectPlace(place)
                } label: {
                    HStack {
                        Text("\(index + 1). \(place.name)")
                            .foregroundStyle(Color.kicap)
                        Spacer()
                        if let rating = place.rating {
                            Text(String(format: "★%.1f", rating))
                                .foregroundStyle(Color.kunyit)
                        }
                    }
                    .font(.makanBody(14))
                }
            }
        }
    }

    private func communityFindsSection(_ items: [NearbyPlace]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            sectionHeader("◇ COMMUNITY FINDS")
            ForEach(items) { place in
                Button {
                    onSelectPlace(place)
                } label: {
                    Text(place.name)
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap)
                }
            }
        }
    }

    private func sectionHeader(_ text: String) -> some View {
        Text(text)
            .font(.makanBody(11))
            .foregroundStyle(.secondary)
            .tracking(0.5)
    }

    // MARK: - Large: full sortable list (this is Nearby's "map/list toggle" — plan correction 12)

    // The CTA sits *outside* the ScrollView (a sibling, not part of the LazyVStack of rows) so
    // it stays visible while the list scrolls — a user 40 rows deep must not lose the primary
    // action (plan: "sticky", not appended after the whole list).
    private var largeContent: some View {
        VStack(spacing: 0) {
            sortRow
            listBody
            pickOneLahButton(compact: false)
                .padding(.horizontal, 16)
                .padding(.vertical, 10)
        }
    }

    private var sortRow: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(NearbySortOption.allCases) { option in
                    Button {
                        sort = option
                    } label: {
                        Text(option.rawValue)
                            .font(.makanBody(12))
                            .foregroundStyle(sort == option ? .white : Color.kicap)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 6)
                            .background(sort == option ? Color.sambalRed : Color.kicap.opacity(0.06))
                            .clipShape(Capsule())
                    }
                }
            }
            .padding(.horizontal, 16)
        }
        .padding(.vertical, 8)
    }

    private var sortedPlaces: [NearbyPlace] {
        switch sort {
        case .defaultOrder:
            return places
        case .rating:
            return places.sorted { ($0.rating ?? -1) > ($1.rating ?? -1) }
        case .price:
            return places.sorted { ($0.priceLevel ?? Int.max) < ($1.priceLevel ?? Int.max) }
        case .distance:
            // Viewport-center distance, not the user's GPS location (plan correction 5) — a
            // user physically at UKM browsing KLCC shouldn't see distances back to UKM.
            guard let browseCenter else { return places }
            let center = CLLocation(latitude: browseCenter.latitude, longitude: browseCenter.longitude)
            return places.sorted {
                center.distance(from: CLLocation(latitude: $0.latitude, longitude: $0.longitude))
                    < center.distance(from: CLLocation(latitude: $1.latitude, longitude: $1.longitude))
            }
        }
    }

    private var listBody: some View {
        ScrollView {
            GeometryReader { proxy in
                Color.clear.preference(key: ScrollOffsetPreferenceKey.self, value: proxy.frame(in: .named("panelList")).minY)
            }
            .frame(height: 0)

            LazyVStack(alignment: .leading, spacing: 0) {
                ForEach(sortedPlaces) { place in
                    Button {
                        onSelectPlace(place)
                    } label: {
                        HStack {
                            VStack(alignment: .leading, spacing: 2) {
                                Text(place.name)
                                    .font(.makanBody(14))
                                    .foregroundStyle(Color.kicap)
                                Text(place.openStatus == "open" ? "Open" : place.openStatus == "closed" ? "Closed" : "")
                                    .font(.makanBody(11))
                                    .foregroundStyle(.secondary)
                            }
                            Spacer()
                            if let rating = place.rating {
                                Text(String(format: "★%.1f", rating))
                                    .font(.makanBody(13))
                                    .foregroundStyle(Color.kunyit)
                            }
                        }
                        .padding(.horizontal, 16)
                        .padding(.vertical, 10)
                    }
                }
            }
        }
        .coordinateSpace(name: "panelList")
        .onPreferenceChange(ScrollOffsetPreferenceKey.self) { listScrollOffset = $0 }
    }

    // MARK: - Gesture arbitration (plan correction 17)
    //
    // Collapsed/medium: a drag anywhere on the panel moves it. Large: the inner list scrolls
    // normally; the panel only collapses on a downward drag once the list is already scrolled
    // to top — checked via the list's own scroll offset, not a second gesture recognizer
    // fighting the ScrollView's.

    private var dragGesture: some Gesture {
        DragGesture(minimumDistance: 8)
            .onChanged { value in
                if state == .large && !listIsAtTop { return }
                dragTranslation = -value.translation.height
            }
            .onEnded { value in
                guard !(state == .large && !listIsAtTop) else {
                    dragTranslation = 0
                    return
                }

                let velocity = value.predictedEndTranslation.height - value.translation.height
                let projectedDelta = -value.translation.height - velocity * 0.2
                snapToNearestState(afterDragging: projectedDelta)
            }
    }

    /// Resets `dragTranslation` in the SAME animated transaction as the `state` change, not
    /// before it — resetting it first (unanimated) would snap the panel instantly back to the
    /// old state's exact height for one frame before the spring even starts, a visible jump/
    /// glitch on every release.
    private func snapToNearestState(afterDragging delta: CGFloat) {
        let candidateHeight = state.height(screenHeight: screenHeight) + delta
        let closest = NearbyPanelState.allCases.min { a, b in
            abs(a.height(screenHeight: screenHeight) - candidateHeight) < abs(b.height(screenHeight: screenHeight) - candidateHeight)
        } ?? .collapsed

        withAnimation(reduceMotion ? .easeOut(duration: 0.12) : .interactiveSpring(response: 0.35, dampingFraction: 0.85)) {
            state = closest
            dragTranslation = 0
        }
    }
}

private struct ScrollOffsetPreferenceKey: PreferenceKey {
    static var defaultValue: CGFloat { 0 }
    static func reduce(value: inout CGFloat, nextValue: () -> CGFloat) {
        value = nextValue()
    }
}
