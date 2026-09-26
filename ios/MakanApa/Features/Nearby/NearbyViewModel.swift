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

    /// Full viewport set, unfiltered by Open now/Budget/Rating — the map always shows every
    /// restaurant in view regardless of which chips are active (backend mirrors this: index()
    /// returns everything, only areaSummary is hard-filtered).
    var places: [NearbyPlace] = []

    /// `places` narrowed by the Open now/Budget/Rating chips — what the area panel's full
    /// sortable list (`.large` state) shows, since that list should still respect them even
    /// though the map itself doesn't.
    var filteredPlaces: [NearbyPlace] {
        places.filter { place in
            if openNowFilter, place.openStatus != "open" { return false }
            if let budgetMaxFilter, let priceLevel = place.priceLevel, priceLevel > budgetMaxFilter { return false }
            if let minRatingFilter {
                guard let rating = place.rating, rating >= minRatingFilter else { return false }
            }
            return true
        }
    }
    /// "What's around here" — set from the exact same `nearbyPlaces()` response `places` came
    /// from, never a second/separate request, so the panel can never disagree with the map.
    var areaSummary: AreaSummaryResponse?
    var selectedPlace: NearbyPlace?
    var placeDetails: PlaceDetails?
    var isLoadingDetails = false
    var apiError: APIError?

    var openNowFilter = false {
        didSet { persistFilters() }
    }
    /// "Hide non-halal" chip — the app-wide Halal-only preference (HalalPreference), not a
    /// Nearby-only filter: it also shapes solo picks and rerolls, and syncs to the account.
    /// Hides confirmed non-halal places server-side; unverified places stay, badged.
    var halalFilter = HalalPreference.isOn {
        didSet {
            guard didLoadFilters, halalFilter != oldValue else { return }
            Task { @MainActor [halalFilter] in HalalPreference.set(halalFilter) }
        }
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
    /// `places`/`areaSummary` were painted from `NearbyPlacesCache` and the fresh fetch hasn't
    /// landed yet — the pills are already on screen, so the view refreshes them silently.
    private(set) var isShowingCachedPlaces = false

    /// Set only while the "🍚 Pick one lah" sequence is running: disables map interaction and
    /// drives the marker pulse/dim/highlight choreography.
    var isPicking = false
    var winnerPlaceId: Int?

    /// Single source of truth for "where is Nearby currently looking" — used both for the next
    /// `places/nearby` fetch and for search, so typing a query after panning + "Search this
    /// area" searches around the new area instead of silently snapping back to GPS.
    private(set) var browseCenter: CLLocationCoordinate2D?

    // MARK: - Search session

    /// Setting this doesn't itself trigger a search — the view calls `scheduleSearch()` on
    /// change (via `.onChange`), since a synchronous `didSet` can't call an actor-isolated method
    /// under Swift 6 strict concurrency.
    var searchQuery: String = ""
    /// The current search, kept across opening/closing a result's sheet — see `SearchSession`.
    private(set) var searchSession: SearchSession?
    private(set) var isSearching = false
    private(set) var searchError: APIError?
    /// How the open place sheet was reached (share link, nudge, search, …) — sent with
    /// "Makan sini" so every entry point is attributed the same way. Nil = browsing the map.
    private(set) var openedPlaceSource: PlaceOpenSource?
    /// The mealtime nudge that opened the current sheet, if any — its funnel gets the sheet's actions.
    private(set) var openedNudgeId: Int?
    /// The search result the open place sheet came from — its address/closing time/branches
    /// paint the sheet immediately. Nil when the sheet was opened from a marker or the panel.
    private(set) var selectedSearchResult: PlaceSearchResult?
    /// A `.googleFallback` result's coordinate, shown as a temporary pin before it resolves.
    var temporarySearchCoordinate: CLLocationCoordinate2D?
    /// The selected search result's canonical place id, once known — kept separate from
    /// `winnerPlaceId` (see `NearbyMapView`).
    var highlightedSearchPlaceId: Int?
    /// The selected search result's coordinate — drives the map's one-off "focus" recenter.
    var focusCoordinate: CLLocationCoordinate2D?
    var focusRequestId = 0
    /// Bumped to make the map fit the top result pins (Show all on map).
    private(set) var fitResultsRequestId = 0
    /// A gentle pan (no zoom change) to a result swiped to in the carousel or tapped on the map.
    private(set) var panCoordinate: CLLocationCoordinate2D?
    private(set) var panRequestId = 0

    private var searchTask: Task<Void, Never>?
    /// Only the newest request may write results — an older, slower response is dropped.
    private var searchRequestSerial = 0
    private static let searchDebounce: Duration = .milliseconds(300)
    private static let minSearchQueryLength = 2
    private static let defaultSearchRadiusKm = 6.0

    /// A camera move the app is about to make, so the settle it causes doesn't raise "Search
    /// this area". Expires quickly in case the camera didn't actually need to move.
    private var pendingMoveOrigin: (origin: MapMoveOrigin, at: Date)?

    /// Result pins for Show all on map — list order, #1 and the selected one called out.
    var searchPins: [SearchPin] {
        guard let session = searchSession, session.presentation == .map else { return [] }
        return session.results.enumerated().map { index, result in
            SearchPin(
                id: result.id, rank: index + 1,
                coordinate: CLLocationCoordinate2D(latitude: result.latitude, longitude: result.longitude),
                // The selected pin is the red one; #1 only stands out until something is selected.
                isTop: index == 0 && session.selectedResultId == nil, isSelected: result.id == session.selectedResultId
            )
        }
    }

    // MARK: - Makan sini

    enum ChoiceState: Equatable {
        case idle, sending, chosen, failed
    }

    private(set) var choiceStates: [Int: ChoiceState] = [:]
    /// One retry key per restaurant per app run — a retried "Makan sini" can't double-record.
    private var choiceIds: [Int: String] = [:]

    private var lastSearchedViewport: MapViewport?
    private var didLoadFilters = false

    /// False until the first `places/nearby` fetch lands — before that an empty map means
    /// "still looking," not "nothing here."
    var hasSearched: Bool { lastSearchedViewport != nil }

    /// Identifies a Nearby fetch by everything that actually changes its result — used to skip
    /// an exact repeat request and to know whether an in-flight fetch is now stale.
    private struct NearbyQueryKey: Equatable {
        let viewport: MapViewport
        let filters: NearbyFilterSignature
    }

    /// The disk cache is only worth consulting for the first fetch — after that `places` is
    /// already current for wherever the map is.
    private var didConsultCache = false

    /// The view fires an unstructured `Task { await viewModel.viewportSettled(...) }` on every
    /// camera-idle event (`NearbyView.swift`) with no cancellation of its own — without this,
    /// several overlapping fetches can be in flight at once, and whichever happens to finish
    /// last wins the race on `places`/`areaSummary`, regardless of which one is actually current.
    private var searchFetchTask: Task<Void, Never>?
    private var lastSucceededQueryKey: NearbyQueryKey?

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
        halalFilter = HalalPreference.isOn
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

        // A move the app made itself (focusing a result, fitting all results) never raises
        // "Search this area" — only the user's own drag/pinch does.
        if let pending = pendingMoveOrigin {
            pendingMoveOrigin = nil
            if pending.origin != .user, Date().timeIntervalSince(pending.at) < 3 {
                showSearchThisArea = false
                if lastSearchedViewport != nil { return }
            }
        }

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
        let key = NearbyQueryKey(
            viewport: viewport,
            filters: NearbyFilterSignature(
                openNow: openNowFilter, budgetMax: budgetMaxFilter, minRating: minRatingFilter,
                mode: discoveryMode, vibe: vibe, halal: halalFilter
            )
        )
        // Already have fresh data for exactly this query — e.g. a redundant "Search this area"
        // tap after nothing actually moved. `lastSucceededQueryKey` only updates on success
        // (below), so a failed fetch never blocks a retry of the same query.
        guard key != lastSucceededQueryKey else { return }

        if !didConsultCache {
            didConsultCache = true
            restoreCachedPlaces(for: key)
        }

        // A genuinely new query supersedes whatever's in flight — cancel it rather than let two
        // responses race to be the one that lands last.
        searchFetchTask?.cancel()
        isLoading = true
        apiError = nil

        let task = Task { [weak self] in
            guard let self else { return }
            do {
                let response = try await APIClient.nearbyPlaces(
                    viewport: viewport, openNow: self.openNowFilter ? true : nil,
                    budgetMax: self.budgetMaxFilter, minRating: self.minRatingFilter,
                    mode: self.discoveryMode, vibe: self.vibe
                )
                guard !Task.isCancelled else { return }
                self.places = response.places.filter { !PlacePreferencesStore.shared.isExcluded($0.id) }
                self.areaSummary = response.areaSummary
                self.lastSearchedViewport = viewport
                self.lastSucceededQueryKey = key
                self.browseCenter = Self.center(of: viewport)
                self.showSearchThisArea = false
                NearbyPlacesCache.save(NearbyPlacesSnapshot(
                    viewport: viewport, filters: key.filters, places: response.places,
                    areaSummary: response.areaSummary, savedAt: Date()
                ))
            } catch let error as APIError {
                guard !Task.isCancelled else { return }
                self.apiError = error
            } catch {
                guard !Task.isCancelled else { return }
                self.apiError = .transport(error)
            }
            guard !Task.isCancelled else { return }
            self.isLoading = false
            self.isShowingCachedPlaces = false
        }
        searchFetchTask = task
        await task.value
    }

    /// Paints the last cached response for this spot while the real fetch runs. Skipped when
    /// something (a shared link's place) already put pins down — the cache must not replace them.
    @MainActor
    private func restoreCachedPlaces(for key: NearbyQueryKey) {
        guard places.isEmpty, let snapshot = NearbyPlacesCache.load(),
              snapshot.isUsable(for: key.viewport, filters: key.filters) else { return }
        places = snapshot.places.filter { !PlacePreferencesStore.shared.isExcluded($0.id) }
        areaSummary = snapshot.areaSummary
        isShowingCachedPlaces = true
    }

    private static func center(of viewport: MapViewport) -> CLLocationCoordinate2D {
        CLLocationCoordinate2D(
            latitude: (viewport.north + viewport.south) / 2,
            longitude: (viewport.east + viewport.west) / 2
        )
    }

    // MARK: - Search capsule

    /// Cancels any in-flight debounce/request before scheduling a new one — only the latest
    /// keystroke's search should ever land. Called by the view on `searchQuery` change. A new
    /// query always starts a new session.
    @MainActor
    func scheduleSearch() {
        searchTask?.cancel()
        let query = searchQuery.trimmingCharacters(in: .whitespaces)
        guard query.count >= Self.minSearchQueryLength else {
            searchRequestSerial += 1
            searchSession = nil
            searchError = nil
            isSearching = false
            return
        }
        guard query != searchSession?.query else { return }
        searchTask = Task { [weak self] in
            try? await Task.sleep(for: Self.searchDebounce)
            guard let self, !Task.isCancelled else { return }
            await self.performSearch(query: query, radiusKm: Self.defaultSearchRadiusKm)
        }
    }

    /// Same query, next radius rung — keeps the session's center so "wider" means wider, not
    /// "wherever the map drifted to".
    @MainActor
    func searchWider() async {
        guard let session = searchSession, let wider = session.meta?.widerRadiusKm else { return }
        await performSearch(query: session.query, radiusKm: wider, center: session.center, presentation: session.presentation)
    }

    /// The explicit "More nearby places — Search Google" row.
    @MainActor
    func searchGoogle() async {
        guard let session = searchSession else { return }
        await performSearch(query: session.query, radiusKm: session.radiusKm, center: session.center, includeGoogle: true, presentation: session.presentation)
    }

    @MainActor
    func retrySearch() async {
        let query = searchSession?.query ?? searchQuery.trimmingCharacters(in: .whitespaces)
        guard query.count >= Self.minSearchQueryLength else { return }
        await performSearch(query: query, radiusKm: searchSession?.radiusKm ?? Self.defaultSearchRadiusKm, center: searchSession?.center)
    }

    /// Runs a suggestion or recent search as if it had been typed.
    @MainActor
    func runSearch(_ query: String) async {
        searchTask?.cancel()
        searchQuery = query
        await performSearch(query: query, radiusKm: Self.defaultSearchRadiusKm)
    }

    @MainActor
    private func performSearch(
        query: String, radiusKm: Double, center: CLLocationCoordinate2D? = nil,
        includeGoogle: Bool? = nil, presentation: SearchSession.Presentation = .list
    ) async {
        guard let center = center ?? browseCenter else { return }
        searchRequestSerial += 1
        let serial = searchRequestSerial
        isSearching = true
        searchError = nil
        defer { if serial == searchRequestSerial { isSearching = false } }

        do {
            let response = try await APIClient.searchPlaces(
                query: query, latitude: center.latitude, longitude: center.longitude,
                radiusKm: radiusKm, includeGoogle: includeGoogle
            )
            guard serial == searchRequestSerial, !Task.isCancelled else { return }
            searchSession = SearchSession(
                query: query, centerLatitude: center.latitude, centerLongitude: center.longitude,
                radiusKm: response.meta?.radiusKm ?? radiusKm,
                results: response.results.filter { result in
                    result.restaurantId.map { !PlacePreferencesStore.shared.isExcluded($0) } ?? true
                },
                meta: response.meta, suggestions: response.suggestions ?? [],
                presentation: presentation, searchedAt: Date()
            )
            if presentation == .map {
                expectProgrammaticMove(.showAllResults)
                fitResultsRequestId += 1
            }
        } catch let error as APIError {
            if serial == searchRequestSerial, !Task.isCancelled { searchError = error }
        } catch {
            if serial == searchRequestSerial, !Task.isCancelled { searchError = .transport(error) }
        }
    }

    /// Resolves a `.googleFallback` result to a canonical restaurant before opening its detail
    /// sheet — canonical/community results already have a `restaurantId` and skip straight
    /// through. The session is kept, so the sheet can go back to these results. Returns nil (and
    /// sets `apiError`) if resolution fails.
    @MainActor
    func selectSearchResult(_ result: PlaceSearchResult) async -> NearbyPlace? {
        searchSession?.selectedResultId = result.id
        if let query = searchSession?.query { RecentSearchStore.record(query) }
        selectedSearchResult = result
        openedPlaceSource = .search
        openedNudgeId = nil

        expectProgrammaticMove(.searchSelection)
        focusCoordinate = CLLocationCoordinate2D(latitude: result.latitude, longitude: result.longitude)
        focusRequestId += 1

        let place: NearbyPlace
        if result.provenance == .googleFallback, let googlePlaceId = result.googlePlaceId {
            temporarySearchCoordinate = focusCoordinate
            do {
                let response = try await APIClient.resolvePlace(googlePlaceId: googlePlaceId)
                temporarySearchCoordinate = nil
                place = response.restaurant
            } catch let error as APIError {
                temporarySearchCoordinate = nil
                apiError = error
                return nil
            } catch {
                temporarySearchCoordinate = nil
                apiError = .transport(error)
                return nil
            }
        } else if let restaurantId = result.restaurantId {
            place = result.asNearbyPlace(id: restaurantId)
        } else {
            return nil
        }

        // The camera is about to leave the loaded area — make sure this place has a pin there.
        if !places.contains(where: { $0.id == place.id }) {
            places.append(place)
        }
        highlightedSearchPlaceId = place.id
        return place
    }

    /// The place sheet was opened some other way (marker, area panel) — it isn't a search result.
    @MainActor
    func clearSelectedSearchResult() {
        selectedSearchResult = nil
        openedPlaceSource = nil
        openedNudgeId = nil
    }

    /// One flow for every "open this place" entry point that isn't a map tap — a shared link, a
    /// mealtime nudge, and later Geng/community links: fetch details, give it a pin, focus the
    /// camera and open its sheet. Returns nil (and sets `apiError`) if it can't be opened.
    @MainActor
    func openPlace(_ request: PlaceOpenRequest) async -> NearbyPlace? {
        do {
            let details = try await APIClient.placeDetails(restaurantId: request.restaurantId)
            guard let latitude = details.latitude, let longitude = details.longitude else { return nil }
            let place = NearbyPlace(
                id: details.id, name: details.name, rating: details.rating, priceLevel: details.priceLevel,
                latitude: latitude, longitude: longitude, openStatus: details.openStatus, halal: nil
            )

            selectedSearchResult = nil
            openedPlaceSource = request.source
            openedNudgeId = request.nudgeId
            if let nudgeId = request.nudgeId {
                Task { _ = try? await APIClient.nudgeEvent(nudgeId: nudgeId, event: "place_opened") }
            }
            if !places.contains(where: { $0.id == place.id }) {
                places.append(place)
            }
            expectProgrammaticMove(.programmatic)
            focusCoordinate = CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
            focusRequestId += 1
            highlightedSearchPlaceId = place.id
            winnerPlaceId = nil
            placeDetails = details
            selectedPlace = place
            return place
        } catch let error as APIError {
            apiError = error
            return nil
        } catch {
            apiError = .transport(error)
            return nil
        }
    }

    @MainActor
    func showAllResultsOnMap() {
        guard let first = searchSession?.results.first else { return }
        searchSession?.presentation = .map
        if searchSession?.selectedResultId == nil {
            searchSession?.selectedResultId = first.id
        }
        expectProgrammaticMove(.showAllResults)
        fitResultsRequestId += 1
    }

    /// Map mode: highlight a result's pin and pan to it, without opening it.
    @MainActor
    func focusSearchResult(_ result: PlaceSearchResult) {
        guard searchSession?.selectedResultId != result.id else { return }
        searchSession?.selectedResultId = result.id
        expectProgrammaticMove(.showAllResults)
        panCoordinate = CLLocationCoordinate2D(latitude: result.latitude, longitude: result.longitude)
        panRequestId += 1
    }

    @MainActor
    func showResultsList() {
        searchSession?.presentation = .list
    }

    @MainActor
    func clearSearch() {
        searchTask?.cancel()
        searchRequestSerial += 1
        searchQuery = ""
        searchSession = nil
        searchError = nil
        selectedSearchResult = nil
        isSearching = false
        temporarySearchCoordinate = nil
        highlightedSearchPlaceId = nil
    }

    @MainActor
    func expectProgrammaticMove(_ origin: MapMoveOrigin) {
        pendingMoveOrigin = (origin, Date())
    }

    /// "Makan sini" — the user's own pick, recorded like an accepted recommendation (Recent,
    /// community signal, Selera). Carries the search context when the place came from search.
    @MainActor
    func makanSini(_ place: NearbyPlace, userLocation: CLLocationCoordinate2D?) async -> ChooseRestaurantResponse? {
        guard choiceStates[place.id] != .sending else { return nil }
        choiceStates[place.id] = .sending
        let choiceId = choiceIds[place.id] ?? UUID().uuidString
        choiceIds[place.id] = choiceId

        let fromSearch = selectedSearchResult?.restaurantId == place.id ? searchSession : nil
        let body = ChooseRestaurantRequestBody(
            clientChoiceId: choiceId,
            installationId: InstallationID.current,
            latitude: userLocation?.latitude, longitude: userLocation?.longitude,
            search: fromSearch.map { .init(query: $0.query, radiusKm: $0.radiusKm, source: $0.source) },
            openedFrom: (openedPlaceSource ?? .nearby).rawValue
        )

        do {
            let response = try await APIClient.chooseRestaurant(id: place.id, body: body)
            choiceStates[place.id] = .chosen
            RecentDecisionStore.shared.record(RecentDecision(
                id: place.id, name: place.name, latitude: place.latitude, longitude: place.longitude,
                foodCategory: placeDetails?.id == place.id ? placeDetails?.foodCategory : nil,
                priceLevel: place.priceLevel, rating: place.rating,
                timestamp: Date(), source: fromSearch == nil ? "nearby" : "search"
            ))
            PendingVibePromptStore.shared.recordAccept(
                decisionId: response.decisionId, clientToken: response.clientToken, restaurantName: place.name
            )
            GuestUpgradeNudge.shared.recordAcceptedPick(decisionId: response.decisionId)
            if let nudgeId = openedNudgeId {
                Task { _ = try? await APIClient.nudgeEvent(nudgeId: nudgeId, event: "makan_sini") }
            }
            return response
        } catch let error as APIError {
            choiceStates[place.id] = .failed
            apiError = error
            return nil
        } catch {
            choiceStates[place.id] = .failed
            apiError = .transport(error)
            return nil
        }
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

        let visibleIds = filteredPlaces.map(\.id)
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
