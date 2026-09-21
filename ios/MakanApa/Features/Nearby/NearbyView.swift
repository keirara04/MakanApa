import SwiftUI
import CoreLocation
import MapKit
import UIKit

struct NearbyView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var soloViewModel
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var viewModel = NearbyViewModel()
    @State private var panelState: NearbyPanelState = .collapsed
    @State private var currentViewport: MapViewport?
    @State private var currentZoom: Float = 15
    @State private var recenterRequestId = 0
    @State private var heartPop = false
    @State private var isSearchActive = false
    @State private var searchPlaceholderExample = NearbyView.searchPlaceholderExamples[0]
    @FocusState private var searchFieldFocused: Bool
    private var preferences = PlacePreferencesStore.shared

    private static let searchPlaceholderExamples = ["nasi lemak", "mamak", "coffee", "chicken rice"]

    var body: some View {
        ZStack(alignment: .top) {
            Color.nasiCream
                .ignoresSafeArea()

            mapLayer
                .ignoresSafeArea(edges: .bottom)
                .zIndex(0)

            VStack(spacing: 10) {
                if isSearchActive {
                    searchCapsule
                    if viewModel.isSearching || !viewModel.searchResults.isEmpty {
                        searchResultsList
                    }
                } else {
                    HStack(alignment: .top, spacing: 8) {
                        primaryRibbon
                        searchIconButton
                    }
                    if viewModel.discoveryMode != .normal {
                        vibeRail
                    }
                }
                if let apiError = viewModel.apiError {
                    errorBanner(for: apiError)
                }
                if viewModel.isZoomedTooFarOut {
                    zoomPrompt
                } else if viewModel.showSearchThisArea {
                    searchThisAreaPill
                }
            }
            .animation(reduceMotion ? .easeOut(duration: 0.12) : Motion.standard, value: isSearchActive)
            .animation(reduceMotion ? .easeOut(duration: 0.12) : Motion.standard, value: viewModel.discoveryMode)
            .padding(.horizontal, 12)
            .padding(.top, 16)
            .zIndex(1)

            // Only while collapsed — the area panel already covers/exceeds this position once
            // it's dragged to medium/large, so floating the button there would just sit behind
            // it (it previously sat low enough to be half-covered by the panel + tab bar, i.e.
            // "tenggelam").
            if panelState == .collapsed {
                HStack {
                    Spacer()
                    VStack {
                        Spacer()
                        recenterButton
                    }
                }
                .padding(.trailing, 12)
                .padding(.bottom, 130)
                .zIndex(1)
            }

            if viewModel.selectedPlace == nil {
                VStack {
                    Spacer()
                    NearbyAreaPanel(
                        summary: viewModel.areaSummary,
                        places: viewModel.places,
                        browseCenter: viewModel.browseCenter,
                        onSelectPlace: { selectPlace($0) },
                        onPickOneLah: { Task { await pickOneLah() } },
                        isPicking: viewModel.isPicking,
                        hasPlaces: !viewModel.places.isEmpty,
                        state: $panelState
                    )
                }
                .padding(.bottom, 6)
                .transition(.move(edge: .bottom).combined(with: .opacity))
                .zIndex(1)
            }
        }
        .animation(Motion.standard, value: viewModel.selectedPlace == nil)
        .sheet(item: $viewModel.selectedPlace) { place in
            placeSheet(for: place)
                .presentationDetents([.medium])
                .presentationDragIndicator(.visible)
        }
        .onAppear {
            if case .authorized = locationService.state {} else {
                locationService.requestLocation()
            }
        }
    }

    // MARK: - Map

    private var mapLayer: some View {
        NearbyMapView(
            places: viewModel.places,
            isPicking: viewModel.isPicking,
            winnerPlaceId: viewModel.winnerPlaceId,
            topRatedIds: viewModel.topRatedIds,
            initialCameraTarget: userCoordinate,
            recenterRequestId: recenterRequestId,
            focusTarget: viewModel.focusCoordinate,
            focusRequestId: viewModel.focusRequestId,
            highlightedSearchPlaceId: viewModel.highlightedSearchPlaceId,
            temporarySearchCoordinate: viewModel.temporarySearchCoordinate,
            onCameraIdle: { viewport, zoom in
                currentViewport = viewport
                currentZoom = zoom
                Task { await viewModel.viewportSettled(viewport, zoom: zoom) }
            },
            onMarkerTapped: { place in
                selectPlace(place)
            }
        )
    }

    /// Shared by a direct marker tap and picking a place from the area panel's Top Rated /
    /// Community Finds / full list sections — one selection path, not two loosely synced ones.
    private func selectPlace(_ place: NearbyPlace) {
        viewModel.placeDetails = nil
        viewModel.selectedPlace = place
        // A leftover "Pick one lah" winner highlight has nothing to do with a place the user is
        // now selecting directly — clear it so only the just-selected pin stays red.
        viewModel.winnerPlaceId = nil
        Task { await viewModel.loadDetails(for: place) }
    }

    private var userCoordinate: CLLocationCoordinate2D? {
        if case let .authorized(coordinate) = locationService.state {
            return coordinate
        }
        return nil
    }

    // MARK: - Filter + discovery mode ribbon
    //
    // One scrollable rail instead of two stacked rows: quick filters and discovery modes are
    // different semantics (hard filter vs. result-shaping mode) so they keep a thin divider
    // between them, but sharing one row halves the vertical space the overlay takes over the map.

    private var primaryRibbon: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                filterChip(label: "Open now", isOn: viewModel.openNowFilter) {
                    viewModel.openNowFilter.toggle()
                    rerunSearch()
                }
                filterChip(label: "≤ RM20", isOn: viewModel.budgetMaxFilter == 2) {
                    viewModel.budgetMaxFilter = viewModel.budgetMaxFilter == 2 ? nil : 2
                    rerunSearch()
                }
                filterChip(label: "4.5+ ★", isOn: viewModel.minRatingFilter == 4.5) {
                    viewModel.minRatingFilter = viewModel.minRatingFilter == 4.5 ? nil : 4.5
                    rerunSearch()
                }

                Rectangle()
                    .fill(Color.kicap.opacity(0.15))
                    .frame(width: 1, height: 20)

                modeChip(.normal, label: "For you")
                modeChip(.lowKey, label: "Low-key")
                modeChip(.cafe, label: "Cafe")
                moreModeMenu
            }
            .padding(.vertical, 3)
        }
        .edgeFade()
    }

    private var moreModeMenu: some View {
        Menu {
            Button("Popular") { setMode(.popular) }
            Button("Cheap eats") { setMode(.cheapEats) }
            Button("Late night") { setMode(.lateNight) }
        } label: {
            Text(moreLabel)
                .font(.makanBody(13))
                .lineLimit(1)
                .foregroundStyle(isMoreModeActive ? .white : Color.kicap)
                .padding(.horizontal, 14)
                .padding(.vertical, 8)
                .background(isMoreModeActive ? Color.sambalRed : Color.white)
                .clipShape(Capsule())
                .shadow(color: .black.opacity(0.08), radius: 4, y: 2)
        }
        .accessibilityLabel(Text(isMoreModeActive ? "\(moreLabel), selected" : moreLabel))
    }

    private var isMoreModeActive: Bool {
        [.popular, .cheapEats, .lateNight].contains(viewModel.discoveryMode)
    }

    private var moreLabel: String {
        switch viewModel.discoveryMode {
        case .popular: return "Popular"
        case .cheapEats: return "Cheap eats"
        case .lateNight: return "Late night"
        default: return "More"
        }
    }

    /// Shared by every filter/mode chip so press-scale, timing, and Reduce Motion behavior stay
    /// consistent instead of being re-implemented per chip.
    private func chip(_ label: String, isOn: Bool, action: @escaping () -> Void) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            withAnimation(reduceMotion ? .easeOut(duration: 0.1) : Motion.quick) { action() }
        } label: {
            Text(label)
                .font(.makanBody(13))
                .lineLimit(1)
                .foregroundStyle(isOn ? .white : Color.kicap)
                .padding(.horizontal, 14)
                .padding(.vertical, 8)
                .background(isOn ? Color.sambalRed : Color.white)
                .clipShape(Capsule())
                .shadow(color: .black.opacity(0.08), radius: 4, y: 2)
        }
        .buttonStyle(MakanApaChipButtonStyle())
        .accessibilityLabel(Text(isOn ? "\(label), selected" : label))
        .accessibilityAddTraits(isOn ? .isSelected : [])
    }

    private func filterChip(label: String, isOn: Bool, action: @escaping () -> Void) -> some View {
        chip(label, isOn: isOn, action: action)
    }

    private func modeChip(_ mode: DiscoveryMode, label: String) -> some View {
        chip(label, isOn: viewModel.discoveryMode == mode) { setMode(mode) }
    }

    private func rerunSearch() {
        guard let currentViewport else { return }
        Task { await viewModel.searchThisAreaTapped(currentViewport) }
    }

    private func setMode(_ mode: DiscoveryMode) {
        viewModel.discoveryMode = mode
        rerunSearch()
    }

    // MARK: - Vibe rail

    /// Lighter weight than `primaryRibbon` on purpose — no big white capsule container, since
    /// this is a contextual refinement of an already-active discovery mode, not a primary control.
    private var vibeRail: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach([Vibe.chill, .study, .coffee, .dessert, .brunch, .lateNight], id: \.self) { option in
                    vibeChip(option)
                }
            }
            .padding(.vertical, 3)
        }
        .edgeFade()
        .transition(reduceMotion ? .opacity : .opacity.combined(with: .move(edge: .top)))
    }

    private func vibeChip(_ option: Vibe) -> some View {
        let isOn = viewModel.vibe == option
        let label = vibeLabel(option)
        return Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            withAnimation(reduceMotion ? .easeOut(duration: 0.1) : Motion.quick) {
                viewModel.vibe = isOn ? nil : option
                rerunSearch()
            }
        } label: {
            Text(label)
                .font(.makanBody(12))
                .lineLimit(1)
                .foregroundStyle(isOn ? .white : Color.kicap.opacity(0.8))
                .padding(.horizontal, 12)
                .padding(.vertical, 6)
                .background(isOn ? Color.kunyit : Color.white.opacity(0.7))
                .clipShape(Capsule())
        }
        .buttonStyle(MakanApaChipButtonStyle())
        .accessibilityLabel(Text(isOn ? "\(label), selected" : label))
        .accessibilityAddTraits(isOn ? .isSelected : [])
    }

    private func vibeLabel(_ vibe: Vibe) -> String {
        switch vibe {
        case .chill: return "☕ Chill"
        case .study: return "📚 Study"
        case .dessert: return "🍰 Dessert"
        case .coffee: return "☕ Coffee"
        case .brunch: return "🥐 Brunch"
        case .lateNight: return "🌙 Late night"
        }
    }

    // MARK: - Search

    private var searchIconButton: some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            searchPlaceholderExample = Self.searchPlaceholderExamples.randomElement() ?? Self.searchPlaceholderExamples[0]
            isSearchActive = true
            searchFieldFocused = true
        } label: {
            Image(systemName: "magnifyingglass")
                .font(.system(size: 16, weight: .medium))
                .foregroundStyle(Color.kicap)
                .frame(width: 40, height: 40)
                .background(.white)
                .clipShape(Circle())
                .shadow(color: .black.opacity(0.08), radius: 4, y: 2)
        }
        .accessibilityLabel("Search places, food, or cuisine")
    }

    /// The search icon morphs in place into this capsule (both live in the same `VStack` slot,
    /// swapped by `isSearchActive`) rather than appearing as a second layer over the ribbon.
    private var searchCapsule: some View {
        HStack(spacing: 8) {
            Image(systemName: "magnifyingglass")
                .foregroundStyle(.secondary)
            TextField(
                "Search \"\(searchPlaceholderExample)\"",
                text: Binding(get: { viewModel.searchQuery }, set: { viewModel.searchQuery = $0 })
            )
            .focused($searchFieldFocused)
            .font(.makanBody(14))
            .submitLabel(.search)
            .autocorrectionDisabled()
            .onChange(of: viewModel.searchQuery) { _, _ in viewModel.scheduleSearch() }

            if viewModel.isSearching {
                ProgressView().controlSize(.small)
            }

            Button {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                closeSearch()
            } label: {
                Image(systemName: "xmark.circle.fill")
                    .foregroundStyle(.secondary)
            }
            .accessibilityLabel("Close search")
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .background(.white)
        .clipShape(Capsule())
        .shadow(color: .black.opacity(0.1), radius: 6, y: 2)
    }

    private func closeSearch() {
        searchFieldFocused = false
        viewModel.clearSearch()
        isSearchActive = false
    }

    private var searchResultsList: some View {
        ScrollView {
            VStack(spacing: 8) {
                ForEach(viewModel.searchResults) { result in
                    searchResultRow(result)
                }
            }
        }
        .frame(maxHeight: 280)
        .animation(reduceMotion ? .easeOut(duration: 0.12) : Motion.quick, value: viewModel.searchResults)
    }

    private func searchResultRow(_ result: PlaceSearchResult) -> some View {
        Button {
            Task { await selectSearchResult(result) }
        } label: {
            HStack(alignment: .top, spacing: 10) {
                VStack(alignment: .leading, spacing: 4) {
                    Text(result.name)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)

                    HStack(spacing: 4) {
                        if let descriptor = result.cuisine ?? result.category {
                            Text(descriptor)
                        }
                        if let spend = PricePresentation.approximateSpendLabel(for: result.priceLevel) {
                            Text("· \(spend)")
                        }
                        if let distanceKm = result.distanceKm {
                            Text(String(format: "· %.1f km", distanceKm))
                        }
                    }
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)

                    if result.provenance == .community {
                        Text("◇ Community find")
                            .font(.makanBody(11))
                            .foregroundStyle(Color.pandan)
                    }
                }

                Spacer()

                if let rating = result.rating {
                    Label(String(format: "%.1f", rating), systemImage: "star.fill")
                        .font(.makanBody(12))
                        .foregroundStyle(Color.kunyit)
                }
            }
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 14))
            .shadow(color: .black.opacity(0.06), radius: 4, y: 2)
        }
        .buttonStyle(SearchResultRowButtonStyle(reduceMotion: reduceMotion))
        .transition(reduceMotion ? .opacity : .opacity.combined(with: .move(edge: .top)))
    }

    /// Resolves `.googleFallback` results to a canonical restaurant (see
    /// `NearbyViewModel.selectSearchResult`) before opening the normal detail sheet — the same
    /// sheet every other marker tap uses, never a separate Google-only view.
    private func selectSearchResult(_ result: PlaceSearchResult) async {
        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
        guard let place = await viewModel.selectSearchResult(result) else { return }
        closeSearch()
        viewModel.placeDetails = nil
        viewModel.selectedPlace = place
        viewModel.winnerPlaceId = nil
        await viewModel.loadDetails(for: place)
    }

    // MARK: - Zoom / search-this-area prompts

    private func errorBanner(for error: APIError) -> some View {
        HStack(spacing: 8) {
            Image(systemName: "wifi.exclamationmark")
            Text(bannerMessage(for: error))
                .font(.makanBody(13))
            Spacer()
            Button {
                viewModel.apiError = nil
            } label: {
                Image(systemName: "xmark")
                    .font(.system(size: 11, weight: .semibold))
            }
        }
        .foregroundStyle(.white)
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .background(Color.sambalRed)
        .clipShape(RoundedRectangle(cornerRadius: 14))
        .shadow(color: .black.opacity(0.1), radius: 4, y: 2)
    }

    private func bannerMessage(for error: APIError) -> String {
        switch error {
        case .transport:
            return Copy.connectionErrorDetail
        default:
            return Copy.genericAPIErrorDetail
        }
    }

    private var zoomPrompt: some View {
        Text("Zoom in to see makan spots")
            .font(.makanBody(13))
            .foregroundStyle(Color.kicap)
            .padding(.horizontal, 16)
            .padding(.vertical, 8)
            .background(.white)
            .clipShape(Capsule())
            .shadow(color: .black.opacity(0.08), radius: 4, y: 2)
    }

    private var searchThisAreaPill: some View {
        Button {
            guard let currentViewport else { return }
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            Task { await viewModel.searchThisAreaTapped(currentViewport) }
        } label: {
            HStack(spacing: 6) {
                if viewModel.isLoading {
                    ProgressView().tint(.white)
                } else {
                    Image(systemName: "arrow.clockwise")
                }
                Text("Search this area")
            }
            .font(.makanBody(13))
            .foregroundStyle(.white)
            .padding(.horizontal, 16)
            .padding(.vertical, 8)
            .background(Color.kicap)
            .clipShape(Capsule())
            .shadow(color: .black.opacity(0.15), radius: 5, y: 2)
        }
    }

    // MARK: - Recenter

    private var recenterButton: some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            if case .authorized = locationService.state {
                recenterRequestId += 1
            } else {
                locationService.requestLocation()
            }
        } label: {
            Image(systemName: "location.fill")
                .font(.system(size: 18, weight: .medium))
                .foregroundStyle(Color.kicap)
                .frame(width: 44, height: 44)
                .background(.white)
                .clipShape(Circle())
                .shadow(color: .black.opacity(0.15), radius: 5, y: 2)
        }
    }

    // MARK: - Pick one lah

    private func pickOneLah() async {
        guard let coordinate = userCoordinate, let viewport = currentViewport else { return }
        let result = await viewModel.pickOneLah(userLocation: coordinate, viewport: viewport)

        // Brief pause after the winner highlight settles before handing off to ResultView —
        // matches the plan's "~300ms pause" beat before the reveal.
        try? await Task.sleep(for: .milliseconds(300))

        soloViewModel.adoptExternalPick(
            decisionId: result.decisionId, clientToken: result.clientToken,
            recommendation: result.recommendation, error: result.error
        )
        router.push(.soloResult)
    }

    // MARK: - Save / Don't suggest

    /// Save stays a plain heart toggle — a soft, reversible signal. "Don't suggest" lives in the
    /// overflow menu instead of sitting next to Save: it's a harder, less-reversible action and
    /// shouldn't visually compete for the same amount of attention.
    @ViewBuilder
    private func placeSheetActions(for place: NearbyPlace) -> some View {
        HStack(spacing: 4) {
            Button {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                preferences.toggleSaved(place)
                withAnimation(Motion.playful) { heartPop = true }
                DispatchQueue.main.asyncAfter(deadline: .now() + 0.18) {
                    withAnimation(Motion.playful) { heartPop = false }
                }
            } label: {
                Image(systemName: preferences.isSaved(place.id) ? "heart.fill" : "heart")
                    .foregroundStyle(preferences.isSaved(place.id) ? Color.sambalRed : .secondary)
                    .font(.system(size: 18))
                    .scaleEffect(heartPop ? 1.2 : 1.0)
                    .frame(width: 32, height: 32)
            }

            Menu {
                Button(role: .destructive) {
                    UIImpactFeedbackGenerator(style: .medium).impactOccurred()
                    withAnimation(Motion.standard) {
                        viewModel.excludePlace(place.id)
                        viewModel.selectedPlace = nil
                    }
                } label: {
                    Label("Don't suggest", systemImage: "eye.slash")
                }
            } label: {
                Image(systemName: "ellipsis")
                    .foregroundStyle(.secondary)
                    .font(.system(size: 16))
                    .frame(width: 32, height: 32)
            }
        }
    }

    // MARK: - Marker tap sheet

    @ViewBuilder
    private func placeSheet(for place: NearbyPlace) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                placePhoto(for: place)

                HStack(alignment: .top) {
                    VStack(alignment: .leading, spacing: 4) {
                        Text(place.name)
                            .font(.makanDisplay(20))
                            .foregroundStyle(Color.kicap)

                        HStack(spacing: 6) {
                            if let rating = place.rating {
                                Label(String(format: "%.1f", rating), systemImage: "star.fill")
                                    .foregroundStyle(Color.kunyit)
                            }
                            if let spend = PricePresentation.approximateSpendLabel(for: place.priceLevel) {
                                Text("· \(spend)")
                            }
                            Text(place.openStatus == "open" ? "· Open" : place.openStatus == "closed" ? "· Closed" : "")
                        }
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                    }

                    Spacer()

                    placeSheetActions(for: place)
                }

                Button {
                    openDirections(to: place)
                } label: {
                    Text("Directions")
                        .font(.makanBody(14))
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(Color.sambalRed)
                        .clipShape(Capsule())
                }

                if viewModel.isLoadingDetails {
                    HStack {
                        Spacer()
                        ProgressView()
                        Spacer()
                    }
                    .padding(.top, 8)
                } else if let details = viewModel.placeDetails {
                    // Google's hero already consumed photos.first — the remaining community
                    // strip only drops that same photo when there was no Google hero to
                    // begin with (i.e. a community photo took its place instead).
                    let remainingCommunityPhotos = details.photos.isEmpty
                        ? Array(details.communityPhotos.dropFirst())
                        : details.communityPhotos
                    if !remainingCommunityPhotos.isEmpty {
                        PlaceCommunityPhotoStrip(urls: remainingCommunityPhotos)
                    }
                    if !details.menuItems.isEmpty {
                        VStack(alignment: .leading, spacing: 8) {
                            Text("MENU").font(.makanBody(11)).foregroundStyle(.secondary).tracking(0.5)
                            PlaceMenuSection(items: details.menuItems)
                        }
                    }
                    if !details.reviews.isEmpty {
                        reviewsSection(for: details)
                    }
                }
            }
            .padding(20)
        }
    }

    @ViewBuilder
    private func placePhoto(for place: NearbyPlace) -> some View {
        // Google photo first (real, free coverage where it exists); a community-contributed
        // photo stands in as the hero for places Google never photographed, instead of always
        // falling straight to the placeholder — see MakanApa#nearby-photo-fallback.
        let heroURL = viewModel.placeDetails?.photos.first.flatMap { URL(string: $0.url) }
            ?? viewModel.placeDetails?.communityPhotos.first.flatMap { URL(string: $0) }

        ZStack {
            if let heroURL {
                RemoteImage(url: heroURL) {
                    placePhotoPlaceholder
                }
                .aspectRatio(contentMode: .fill)
            } else if viewModel.isLoadingDetails {
                placePhotoPlaceholder
            } else {
                QuickAddPhotoTile(restaurantId: place.id)
            }
        }
        .frame(height: 160)
        .frame(maxWidth: .infinity)
        .background(Color.kicap.opacity(0.06))
        .clipShape(RoundedRectangle(cornerRadius: 20))
    }

    private var placePhotoPlaceholder: some View {
        Image(systemName: "fork.knife")
            .font(.system(size: 36))
            .foregroundStyle(Color.kunyit)
    }

    @ViewBuilder
    private func reviewsSection(for details: PlaceDetails) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Reviews from Google Maps")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)

            ForEach(Array(details.reviews.prefix(2).enumerated()), id: \.offset) { _, review in
                VStack(alignment: .leading, spacing: 4) {
                    if let rating = review.rating {
                        Text(String(repeating: "★", count: Int(rating.rounded())))
                            .font(.system(size: 11))
                            .foregroundStyle(Color.kunyit)
                    }
                    Text("\"\(review.text.count > 120 ? String(review.text.prefix(120)).trimmingCharacters(in: .whitespaces) + "…" : review.text)\"")
                        .font(.makanBody(13))
                        .italic()
                        .foregroundStyle(Color.kicap.opacity(0.85))
                    Text("— \(review.authorName)")
                        .font(.makanBody(11))
                        .foregroundStyle(.secondary)
                }
            }
        }
        .padding(14)
        .background(Color.kicap.opacity(0.04))
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }

    /// `MapKit` is only used here for the "Directions" hand-off (`MKMapItem.openInMaps()`), not
    /// for rendering — Google Places-sourced content stays on the Google map per Places API terms.
    private func openDirections(to place: NearbyPlace) {
        let coordinate = CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude)
        let placemark = MKPlacemark(coordinate: coordinate)
        let mapItem = MKMapItem(placemark: placemark)
        mapItem.name = place.name
        mapItem.openInMaps()
    }
}

/// Press-scale (0.96) + `Motion.quick` shared by every filter/mode/vibe chip, so the three chip
/// builders only own content and selected-state, not duplicated press-animation code.
private struct MakanApaChipButtonStyle: ButtonStyle {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.96 : 1.0)
            .animation(Motion.quick, value: configuration.isPressed)
    }
}

private struct SearchResultRowButtonStyle: ButtonStyle {
    let reduceMotion: Bool

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.985 : 1.0)
            .animation(Motion.quick, value: configuration.isPressed)
    }
}

/// Fades the leading/trailing few percent of a horizontally-scrolling rail to transparent, so a
/// partially-visible chip reads as "swipe for more" instead of looking clipped.
private struct EdgeFadeModifier: ViewModifier {
    func body(content: Content) -> some View {
        content.mask(
            LinearGradient(
                stops: [
                    .init(color: .clear, location: 0),
                    .init(color: .black, location: 0.04),
                    .init(color: .black, location: 0.96),
                    .init(color: .clear, location: 1),
                ],
                startPoint: .leading, endPoint: .trailing
            )
        )
    }
}

private extension View {
    func edgeFade() -> some View { modifier(EdgeFadeModifier()) }
}
