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

    var isZoomedTooFarOut = false
    var showSearchThisArea = false
    var isLoading = false

    /// Set only while the "🍚 Pick one lah" sequence is running: disables map interaction and
    /// drives the marker pulse/dim/highlight choreography.
    var isPicking = false
    var winnerPlaceId: Int?

    private var lastSearchedViewport: MapViewport?
    private var didLoadFilters = false

    private static let openNowKey = "NearbyViewModel.openNowFilter"
    private static let budgetMaxKey = "NearbyViewModel.budgetMaxFilter"
    private static let minRatingKey = "NearbyViewModel.minRatingFilter"

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
        didLoadFilters = true
    }

    private func persistFilters() {
        guard didLoadFilters else { return }
        let defaults = UserDefaults.standard
        defaults.set(openNowFilter, forKey: Self.openNowKey)
        defaults.set(budgetMaxFilter, forKey: Self.budgetMaxKey)
        defaults.set(minRatingFilter, forKey: Self.minRatingKey)
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
                budgetMax: budgetMaxFilter, minRating: minRatingFilter
            )
            places = response.places.filter { !PlacePreferencesStore.shared.isExcluded($0.id) }
            lastSearchedViewport = viewport
            showSearchThisArea = false
        } catch let error as APIError {
            apiError = error
        } catch {
            apiError = .transport(error)
        }
        isLoading = false
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
                openNow: openNowFilter ? true : nil, budgetMax: budgetMaxFilter, minRating: minRatingFilter
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
