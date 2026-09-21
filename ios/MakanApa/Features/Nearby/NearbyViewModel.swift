import Foundation
import Observation
import CoreLocation

@Observable
final class NearbyViewModel {
    /// Below this zoom level we don't fetch/render individual markers — see plan Phase 2,
    /// "zoom-aware display." Tune in Simulator; not meant to be exact on the first pass.
    static let minZoomForMarkers: Float = 14

    /// A re-search viewport must differ from the last searched one by at least this fraction
    /// of its own span before "Search this area" appears — protects against a tiny accidental
    /// pan re-triggering the pill (and, downstream, a Places call) every time.
    private static let significantMoveFraction = 0.35

    var places: [NearbyPlace] = []
    /// "What's around here" — set from the exact same `nearbyPlaces()` response `places` came
    /// from, never a second/separate request, so the panel can never disagree with the map.
    var areaSummary: AreaSummaryResponse?
    /// Ids from `areaSummary.topRated`, refreshed only on camera-idle (inside `search()`) so
    /// pin emphasis doesn't reshuffle mid-drag.
    var topRatedIds: Set<Int> = []
    var selectedPlace: NearbyPlace?
    var placeDetails: PlaceDetails?
    var isLoadingDetails = false
    var apiError: APIError?

    var openNowFilter = false {
        didSet { persistFilters() }
    }
    var budgetMaxFilter: Int? {
        didSet { persistFilters() }
    }
    var minRatingFilter: Double? {
        didSet { persistFilters() }
    }
    var discoveryMode: DiscoveryMode = .normal {
        didSet {
            // Enforced here, not in the view — holds regardless of which UI surface changes the
            // mode. A vibe selected under Low-key/Cafe shouldn't silently keep skewing "For you"
            // results after switching back, with no visible indicator that it's still active.
            if discoveryMode == .normal { vibe = nil }
            persistFilters()
        }
    }
    var vibe: Vibe? {
        didSet { persistFilters() }
    }

    var isZoomedTooFarOut = false
    var showSearchThisArea = false
    var isLoading = false

    /// Set only while the "🍚 Pick one lah" sequence is running: disables map interaction and
    /// drives the marker pulse/dim/highlight choreography.
    var isPicking = false
    var winnerPlaceId: Int?

    /// Single source of truth for "where is Nearby currently looking" — used both for the next
    /// `places/nearby` fetch and for search, so typing a query after panning + "Search this
    /// area" searches around the new area instead of silently snapping back to GPS.
    private(set) var browseCenter: CLLocationCoordinate2D?

    // MARK: - Search capsule

    /// Setting this doesn't itself trigger a search — the view calls `scheduleSearch()` on
    /// change (via `.onChange`), since a synchronous `didSet` can't call an actor-isolated method
    /// under Swift 6 strict concurrency.
    var searchQuery: String = ""
    var searchResults: [PlaceSearchResult] = []
    var isSearching = false
    /// A `.googleFallback` result's coordinate, shown as a temporary pin before it resolves.
    var temporarySearchCoordinate: CLLocationCoordinate2D?
    /// The selected search result's canonical place id, once known — kept separate from
    /// `winnerPlaceId` (see `NearbyMapView`).
    var highlightedSearchPlaceId: Int?
    /// The selected search result's coordinate — drives the map's one-off "focus" recenter.
    var focusCoordinate: CLLocationCoordinate2D?
    var focusRequestId = 0

    private var searchTask: Task<Void, Never>?
    private static let searchDebounce: Duration = .milliseconds(350)
    private static let minSearchQueryLength = 2

    private var lastSearchedViewport: MapViewport?
    private var didLoadFilters = false

    private static let openNowKey = "NearbyViewModel.openNowFilter"
    private static let budgetMaxKey = "NearbyViewModel.budgetMaxFilter"
    private static let minRatingKey = "NearbyViewModel.minRatingFilter"
    private static let discoveryModeKey = "NearbyViewModel.discoveryMode"
    private static let vibeKey = "NearbyViewModel.vibe"

    init() {
        loadFilters()
    }

    /// Guarded by `didLoadFilters` so the initial read-back from `UserDefaults` (each filter's
    /// `didSet` firing during `loadFilters()` itself) doesn't immediately re-write the same
    /// values it just loaded.
    private func loadFilters() {
        let defaults = UserDefaults.standard
        openNowFilter = defaults.bool(forKey: Self.openNowKey)
        budgetMaxFilter = defaults.object(forKey: Self.budgetMaxKey) as? Int
        minRatingFilter = defaults.object(forKey: Self.minRatingKey) as? Double
        discoveryMode = (defaults.string(forKey: Self.discoveryModeKey)).flatMap(DiscoveryMode.init) ?? .normal
        vibe = (defaults.string(forKey: Self.vibeKey)).flatMap(Vibe.init)
        // Self-heals a persisted state from before this invariant existed (mode=normal with a
        // leftover vibe) instead of carrying it forward indefinitely.
        if discoveryMode == .normal { vibe = nil }
        didLoadFilters = true
    }

    private func persistFilters() {
        guard didLoadFilters else { return }
        let defaults = UserDefaults.standard
        defaults.set(openNowFilter, forKey: Self.openNowKey)
        defaults.set(budgetMaxFilter, forKey: Self.budgetMaxKey)
        defaults.set(minRatingFilter, forKey: Self.minRatingKey)
        defaults.set(discoveryMode.rawValue, forKey: Self.discoveryModeKey)
        defaults.set(vibe?.rawValue, forKey: Self.vibeKey)
    }

    @MainActor
    func viewportSettled(_ viewport: MapViewport, zoom: Float) async {
        isZoomedTooFarOut = zoom < Self.minZoomForMarkers
        if isZoomedTooFarOut {
            showSearchThisArea = false
            return
        }

        guard let last = lastSearchedViewport else {
            await search(viewport)
            return
        }

        showSearchThisArea = Self.viewportMoved(from: last, to: viewport)
    }

    @MainActor
    func searchThisAreaTapped(_ viewport: MapViewport) async {
        await search(viewport)
    }

    /// Only fetches photo/reviews for the one marker the user actually tapped — never for the
    /// whole visible list, mirroring the same "winner-only enrichment" rule Decide uses.
    @MainActor
    func loadDetails(for place: NearbyPlace) async {
        isLoadingDetails = true
        do {
            placeDetails = try await APIClient.placeDetails(restaurantId: place.id)
        } catch {
            placeDetails = nil
        }
        isLoadingDetails = false
    }

    /// "Don't suggest" needs to drop the place from view immediately, not just on the next
    /// search — the marker is still on screen and the sheet is still open when the user taps it.
    @MainActor
    func excludePlace(_ placeId: Int) {
        PlacePreferencesStore.shared.exclude(placeId)
        places.removeAll { $0.id == placeId }
    }

    @MainActor
    private func search(_ viewport: MapViewport) async {
        isLoading = true
        apiError = nil
        do {
            let response = try await APIClient.nearbyPlaces(
                viewport: viewport, openNow: openNowFilter ? true : nil,
                budgetMax: budgetMaxFilter, minRating: minRatingFilter,
                mode: discoveryMode, vibe: vibe
            )
            places = response.places.filter { !PlacePreferencesStore.shared.isExcluded($0.id) }
            areaSummary = response.areaSummary
            topRatedIds = Set(response.areaSummary.topRated.map(\.id))
            lastSearchedViewport = viewport
            browseCenter = Self.center(of: viewport)
            showSearchThisArea = false
        } catch let error as APIError {
            apiError = error
        } catch {
            apiError = .transport(error)
        }
        isLoading = false
    }

    private static func center(of viewport: MapViewport) -> CLLocationCoordinate2D {
        CLLocationCoordinate2D(
            latitude: (viewport.north + viewport.south) / 2,
            longitude: (viewport.east + viewport.west) / 2
        )
    }

    // MARK: - Search capsule

    /// Cancels any in-flight debounce/request before scheduling a new one — only the latest
    /// keystroke's search should ever land. Called by the view on `searchQuery` change.
    @MainActor
    func scheduleSearch() {
        searchTask?.cancel()
        let query = searchQuery.trimmingCharacters(in: .whitespaces)
        guard query.count >= Self.minSearchQueryLength else {
            searchResults = []
            isSearching = false
            return
        }
        searchTask = Task { [weak self] in
            try? await Task.sleep(for: Self.searchDebounce)
            guard let self, !Task.isCancelled else { return }
            await self.performSearch(query: query)
        }
    }

    @MainActor
    private func performSearch(query: String) async {
        guard let center = browseCenter else { return }
        isSearching = true
        do {
            let response = try await APIClient.searchPlaces(
                query: query, latitude: center.latitude, longitude: center.longitude
            )
            guard !Task.isCancelled else { return }
            searchResults = response.results
        } catch let error as APIError {
            if !Task.isCancelled { apiError = error }
        } catch {
            if !Task.isCancelled { apiError = .transport(error) }
        }
        isSearching = false
    }

    /// Resolves a `.googleFallback` result to a canonical restaurant before opening its detail
    /// sheet — canonical/community results already have a `restaurantId` and skip straight
    /// through. Returns nil (and sets `apiError`) if resolution fails.
    @MainActor
    func selectSearchResult(_ result: PlaceSearchResult) async -> NearbyPlace? {
        focusCoordinate = CLLocationCoordinate2D(latitude: result.latitude, longitude: result.longitude)
        focusRequestId += 1

        if result.provenance == .googleFallback, let googlePlaceId = result.googlePlaceId {
            temporarySearchCoordinate = focusCoordinate
            do {
                let response = try await APIClient.resolvePlace(googlePlaceId: googlePlaceId)
                temporarySearchCoordinate = nil
                highlightedSearchPlaceId = response.restaurant.id
                return response.restaurant
            } catch let error as APIError {
                apiError = error
                return nil
            } catch {
                apiError = .transport(error)
                return nil
            }
        }

        guard let restaurantId = result.restaurantId else { return nil }
        highlightedSearchPlaceId = restaurantId
        return NearbyPlace(
            id: restaurantId, name: result.name, rating: result.rating, priceLevel: result.priceLevel,
            latitude: result.latitude, longitude: result.longitude, openStatus: result.openStatus ?? "unknown"
        )
    }

    @MainActor
    func clearSearch() {
        searchTask?.cancel()
        searchQuery = ""
        searchResults = []
        isSearching = false
        temporarySearchCoordinate = nil
        highlightedSearchPlaceId = nil
    }

    /// Runs the "🍚 Pick one lah" sequence: pulses the currently visible candidates while the
    /// network request is in flight, then reports the winner (or nil) once both the minimum
    /// animation time and the response have completed — never waits on the network alone, so a
    /// fast response still gets the full pulse instead of feeling clipped.
    @MainActor
    func pickOneLah(
        userLocation: CLLocationCoordinate2D, viewport: MapViewport
    ) async -> (decisionId: Int?, clientToken: String?, recommendation: RecommendationResponse.Recommendation?, error: APIError?) {
        isPicking = true
        winnerPlaceId = nil
        defer { isPicking = false }

        let minimumPulseDuration: Duration = .milliseconds(600)
        let start = ContinuousClock.now

        let visibleIds = places.map(\.id)
        guard !visibleIds.isEmpty else {
            return (nil, nil, nil, nil)
        }

        do {
            let response = try await APIClient.pickFromVisible(
                latitude: userLocation.latitude, longitude: userLocation.longitude,
                viewport: viewport, visiblePlaceIds: visibleIds,
                openNow: openNowFilter ? true : nil, budgetMax: budgetMaxFilter, minRating: minRatingFilter,
                mode: discoveryMode, vibe: vibe
            )

            let elapsed = ContinuousClock.now - start
            if elapsed < minimumPulseDuration {
                try? await Task.sleep(for: minimumPulseDuration - elapsed)
            }

            winnerPlaceId = response.recommendation?.id
            return (response.decisionId, response.clientToken, response.recommendation, nil)
        } catch let error as APIError {
            return (nil, nil, nil, error)
        } catch {
            return (nil, nil, nil, .transport(error))
        }
    }

    /// True if the new viewport's center moved (relative to the last search's span) by more
    /// than `significantMoveFraction`, or its size changed enough to matter — small accidental
    /// pans/zooms shouldn't surface "Search this area."
    private static func viewportMoved(from last: MapViewport, to current: MapViewport) -> Bool {
        let lastLatSpan = last.north - last.south
        let lastLonSpan = last.east - last.west
        guard lastLatSpan > 0, lastLonSpan > 0 else { return true }

        let lastCenterLat = (last.north + last.south) / 2
        let lastCenterLon = (last.east + last.west) / 2
        let currentCenterLat = (current.north + current.south) / 2
        let currentCenterLon = (current.east + current.west) / 2

        let latMoveFraction = abs(currentCenterLat - lastCenterLat) / lastLatSpan
        let lonMoveFraction = abs(currentCenterLon - lastCenterLon) / lastLonSpan

        return latMoveFraction > significantMoveFraction || lonMoveFraction > significantMoveFraction
    }
}
