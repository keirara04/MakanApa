import SwiftUI
import UIKit

/// Search mode's full-height results panel — replaces the old small list floating over the map.
/// Owns every state a search can be in: empty field (recents + suggestions), loading, results,
/// no results, error. Pure view: all actions go back out through closures.
struct NearbySearchResultsPanel: View {
    let query: String
    let session: SearchSession?
    let isSearching: Bool
    let error: APIError?
    let recentSearches: [String]
    let onSelect: (PlaceSearchResult) -> Void
    let onRunSearch: (String) -> Void
    let onRemoveRecent: (String) -> Void
    let onSearchWider: () -> Void
    let onSearchGoogle: () -> Void
    let onShowOnMap: () -> Void
    let onRetry: () -> Void
    let onAddPlace: () -> Void

    private static let quickSuggestions = ["nasi lemak", "mamak", "coffee", "chicken rice"]

    private var trimmedQuery: String { query.trimmingCharacters(in: .whitespaces) }

    var body: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: 10) {
                content
            }
            .padding(.horizontal, 4)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.immediately)
        .scrollIndicators(.hidden)
    }

    @ViewBuilder
    private var content: some View {
        if trimmedQuery.count < 2 {
            startState
        } else if let error, session?.query != trimmedQuery || session?.results.isEmpty != false {
            errorState(error)
        } else if let session, session.query == trimmedQuery {
            if session.results.isEmpty {
                noResultsState(session)
            } else {
                resultsState(session)
            }
        } else if isSearching {
            loadingState
        }
    }

    // MARK: - States

    private var startState: some View {
        VStack(alignment: .leading, spacing: 18) {
            if !recentSearches.isEmpty {
                VStack(alignment: .leading, spacing: 4) {
                    sectionLabel("Recent")
                    ForEach(recentSearches, id: \.self) { recent in
                        HStack {
                            Button {
                                onRunSearch(recent)
                            } label: {
                                Label(recent, systemImage: "clock.arrow.circlepath")
                                    .font(.makanBody(15))
                                    .foregroundStyle(Color.kicap)
                                    .frame(maxWidth: .infinity, minHeight: 40, alignment: .leading)
                                    .contentShape(Rectangle())
                            }
                            .buttonStyle(.plain)
                            Button {
                                onRemoveRecent(recent)
                            } label: {
                                Image(systemName: "xmark")
                                    .font(.system(size: 12, weight: .semibold))
                                    .foregroundStyle(.secondary)
                                    .frame(width: 36, height: 36)
                            }
                            .accessibilityLabel("Remove \(recent) from recent searches")
                        }
                    }
                }
            }

            VStack(alignment: .leading, spacing: 8) {
                sectionLabel("Try")
                chipRow(Self.quickSuggestions)
            }
        }
        .padding(16)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }

    private var loadingState: some View {
        VStack(spacing: 8) {
            ForEach(0..<4, id: \.self) { _ in
                RoundedRectangle(cornerRadius: 14)
                    .fill(Color.white)
                    .frame(height: 78)
                    .overlay(alignment: .leading) {
                        VStack(alignment: .leading, spacing: 8) {
                            Capsule().fill(Color.kicap.opacity(0.08)).frame(width: 140, height: 12)
                            Capsule().fill(Color.kicap.opacity(0.06)).frame(width: 200, height: 10)
                        }
                        .padding(.horizontal, 14)
                    }
            }
        }
        .redacted(reason: .placeholder)
        .accessibilityLabel("Searching")
    }

    private func errorState(_ error: APIError) -> some View {
        VStack(spacing: 10) {
            Text(error.userFacingCopy.detail)
                .font(.makanBody(14))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            Button(Copy.tryAgain, action: onRetry)
                .font(.makanBody(14))
                .foregroundStyle(Color.sambalRed)
                .frame(minHeight: 44)
        }
        .frame(maxWidth: .infinity)
        .padding(20)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }

    private func noResultsState(_ session: SearchSession) -> some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("No “\(session.query)” within \(Self.radiusLabel(session.radiusKm)).")
                .font(.makanBody(15))
                .foregroundStyle(Color.kicap)

            HStack(spacing: 10) {
                if let wider = session.meta?.widerRadiusKm {
                    pillButton("Search within \(Self.radiusLabel(wider))", systemImage: "arrow.up.left.and.arrow.down.right", prominent: true, action: onSearchWider)
                }
                pillButton("Add this place", systemImage: "plus", prominent: session.meta?.widerRadiusKm == nil, action: onAddPlace)
            }

            if session.meta?.googleAvailable == true {
                Button(action: onSearchGoogle) {
                    Label("Search Google for “\(session.query)”", systemImage: "globe")
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                }
                .frame(minHeight: 36)
            }

            if !session.suggestions.isEmpty {
                VStack(alignment: .leading, spacing: 8) {
                    sectionLabel("Maybe try")
                    chipRow(session.suggestions)
                }
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }

    @ViewBuilder
    private func resultsState(_ session: SearchSession) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 8) {
            Text("\(session.results.count) \(session.results.count == 1 ? "result" : "results") · within \(Self.radiusLabel(session.radiusKm))")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
            if let wider = session.meta?.widerRadiusKm {
                Button("Wider (\(Self.radiusLabel(wider)))", action: onSearchWider)
                    .font(.makanBody(13))
                    .foregroundStyle(Color.sambalRed)
            }
            Spacer()
            Button(action: onShowOnMap) {
                Label("Map", systemImage: "map")
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(Color.white)
                    .clipShape(Capsule())
            }
            .accessibilityLabel("Show all results on the map")
        }
        .padding(.horizontal, 4)

        ForEach(Array(session.results.enumerated()), id: \.element.id) { index, result in
            if session.startsGroup(at: index) {
                Text("\(result.name) · \(result.groupSize) nearby")
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
                    .padding(.horizontal, 4)
                    .padding(.top, index == 0 ? 0 : 6)
            }
            Button {
                onSelect(result)
            } label: {
                SearchResultRow(result: result, query: session.query)
            }
            .buttonStyle(SearchResultPressStyle())
        }

        if session.meta?.googleAvailable == true {
            VStack(alignment: .leading, spacing: 6) {
                Text("More nearby places")
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                Button(action: onSearchGoogle) {
                    Label("Search Google", systemImage: "globe")
                        .font(.makanBody(14))
                        .foregroundStyle(Color.sambalRed)
                }
                .frame(minHeight: 36)
            }
            .padding(.horizontal, 4)
            .padding(.top, 6)
        }
    }

    // MARK: - Pieces

    private func sectionLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.makanBody(11))
            .tracking(0.5)
            .foregroundStyle(.secondary)
    }

    private func chipRow(_ items: [String]) -> some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(items, id: \.self) { item in
                    Button {
                        onRunSearch(item)
                    } label: {
                        Text(item)
                            .font(.makanBody(13))
                            .foregroundStyle(Color.kicap)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 8)
                            .background(Color.kicap.opacity(0.06))
                            .clipShape(Capsule())
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private func pillButton(_ title: String, systemImage: String, prominent: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Label(title, systemImage: systemImage)
                .font(.makanBody(13))
                .foregroundStyle(prominent ? .white : Color.kicap)
                .padding(.horizontal, 12)
                .padding(.vertical, 9)
                .background(prominent ? Color.sambalRed : Color.kicap.opacity(0.06))
                .clipShape(Capsule())
        }
        .buttonStyle(.plain)
    }

    static func radiusLabel(_ km: Double) -> String {
        "\(km.formatted(.number.precision(.fractionLength(0...1)))) km"
    }
}

/// One search result. Name stays on its own line; the location line is what tells two branches
/// of the same chain apart, falling back to category and finally distance alone.
struct SearchResultRow: View {
    let result: PlaceSearchResult
    let query: String
    /// Content only, no card background — for embedding inside another card.
    var bare = false

    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            VStack(alignment: .leading, spacing: 4) {
                Text(highlightedName)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap)
                    .lineLimit(1)

                if let location = locationLine {
                    Text(location)
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }

                HStack(spacing: 6) {
                    if let status = openStatusText {
                        Text(status.text)
                            .foregroundStyle(status.color)
                    }
                    if let spend = PricePresentation.approximateSpendLabel(for: result.priceLevel) {
                        Text(spend).foregroundStyle(.secondary)
                    }
                    if let halal = result.halal?.display, result.halal?.status != .unknown {
                        HalalBadge(display: halal)
                    }
                    if result.isCommunityFind {
                        Text("◇ Community find").foregroundStyle(Color.pandan)
                    }
                }
                .font(.makanBody(12))
                .lineLimit(1)
            }

            Spacer(minLength: 8)

            if let rating = result.rating {
                Label(String(format: "%.1f", rating), systemImage: "star.fill")
                    .font(.makanBody(12))
                    .foregroundStyle(Color.kunyit)
                    .labelStyle(.titleAndIcon)
            }
        }
        .padding(bare ? 0 : 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(bare ? Color.clear : Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 14))
        .shadow(color: .black.opacity(bare ? 0 : 0.06), radius: 4, y: 2)
        .accessibilityElement(children: .combine)
    }

    /// Address · distance, else category · distance, else distance — never a line that starts
    /// with a stray separator.
    private var locationLine: String? {
        let lead = result.address ?? result.category ?? result.cuisine
        let distance = result.distanceKm.map { String(format: "%.1f km", $0) }
        let parts = [lead, distance].compactMap { $0 }.filter { !$0.isEmpty }
        return parts.isEmpty ? nil : parts.joined(separator: " · ")
    }

    private var openStatusText: (text: String, color: Color)? {
        switch result.openStatus {
        case "open":
            return (result.closesAt.map { "Open · closes \($0)" } ?? "Open", Color.pandan)
        case "closed":
            return ("Closed", Color.sambalRed)
        default:
            return nil
        }
    }

    /// The part of the name that matched the query in bold.
    private var highlightedName: AttributedString {
        var attributed = AttributedString(result.name)
        let needle = query.trimmingCharacters(in: .whitespaces)
        if !needle.isEmpty, let range = attributed.range(of: needle, options: [.caseInsensitive, .diacriticInsensitive]) {
            attributed[range].font = .makanBody(15).weight(.heavy)
        }
        return attributed
    }
}

/// Map mode's bottom strip — the same results in the same order, one card per pin, paging like
/// Google Maps: swiping to a card highlights and pans to its pin (`onFocus`), tapping a pin
/// scrolls here, and tapping a card opens the place (`onOpen`).
struct SearchResultsCarousel: View {
    let session: SearchSession
    let onFocus: (PlaceSearchResult) -> Void
    let onOpen: (PlaceSearchResult) -> Void

    @State private var scrolledId: String?

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            // Plain HStack, not Lazy: a lazy stack estimates its height before measuring every
            // card, so the strip came out shorter than the tallest card and clipped its top.
            // Results are capped server-side, so building them all is cheap.
            HStack(alignment: .top, spacing: 10) {
                ForEach(Array(session.results.enumerated()), id: \.element.id) { index, result in
                    Button {
                        onOpen(result)
                    } label: {
                        SearchResultCard(
                            result: result, rank: index + 1, query: session.query,
                            isHighlighted: result.id == (session.selectedResultId ?? session.results.first?.id)
                        )
                    }
                    .buttonStyle(SearchResultPressStyle())
                    .containerRelativeFrame(.horizontal) { width, _ in width * 0.86 }
                    .id(result.id)
                }
            }
            .scrollTargetLayout()
            // Room for the card shadows — the scroll view would otherwise clip them flat.
            .padding(.vertical, 10)
        }
        .contentMargins(.horizontal, 16, for: .scrollContent)
        .scrollClipDisabled()
        .scrollTargetBehavior(.viewAligned)
        .scrollPosition(id: $scrolledId, anchor: .center)
        // A horizontal ScrollView is greedy vertically — without this it takes the whole screen
        // height and centers the cards mid-map.
        .fixedSize(horizontal: false, vertical: true)
        .onAppear { scrolledId = session.selectedResultId ?? session.results.first?.id }
        .onChange(of: scrolledId) { _, id in
            guard let id, id != session.selectedResultId, let result = session.results.first(where: { $0.id == id }) else { return }
            onFocus(result)
        }
        .onChange(of: session.selectedResultId) { _, id in
            // A pin tap selected something — bring its card into view.
            guard let id, id != scrolledId else { return }
            withAnimation(Motion.standard) { scrolledId = id }
        }
    }
}

/// One carousel card: rank badge matching the pin, the row content, and a clear "open" cue.
private struct SearchResultCard: View {
    let result: PlaceSearchResult
    let rank: Int
    let query: String
    let isHighlighted: Bool

    var body: some View {
        HStack(alignment: .center, spacing: 10) {
            Text("\(rank)")
                .font(.makanBody(13).weight(.heavy))
                .foregroundStyle(isHighlighted ? .white : Color.kicap)
                .frame(width: 28, height: 28)
                .background(isHighlighted ? Color.sambalRed : Color.kicap.opacity(0.08))
                .clipShape(Circle())
                .accessibilityHidden(true)

            SearchResultRow(result: result, query: query, bare: true)

            Image(systemName: "chevron.right")
                .font(.system(size: 13, weight: .semibold))
                .foregroundStyle(Color.kicap.opacity(0.35))
        }
        .padding(12)
        // Every card the height of the tallest, so the strip doesn't jump while swiping.
        .frame(maxHeight: .infinity)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .overlay {
            RoundedRectangle(cornerRadius: 18)
                .stroke(isHighlighted ? Color.sambalRed.opacity(0.6) : .clear, lineWidth: 2)
        }
        .shadow(color: .black.opacity(0.12), radius: 8, y: 3)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("Result \(rank), \(result.name)")
        .accessibilityHint("Opens this place")
    }
}

struct SearchResultPressStyle: ButtonStyle {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.98 : 1.0)
            .animation(Motion.quick, value: configuration.isPressed)
    }
}
