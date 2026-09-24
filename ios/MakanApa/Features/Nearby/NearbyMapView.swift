import SwiftUI
import GoogleMaps
import UIKit

/// Thin `UIViewRepresentable` wrapper around `GMSMapView`. Owns marker lifecycle (branded
/// rating-bubble icons, the pick-one-lah pulse/dim/highlight sequence) and reports viewport
/// changes back up via `onCameraIdle` — the view model decides what to do with them (fetch,
/// or show "Search this area"), this view just reports facts about the map.
struct NearbyMapView: UIViewRepresentable {
    let places: [NearbyPlace]
    let isPicking: Bool
    let winnerPlaceId: Int?
    let initialCameraTarget: CLLocationCoordinate2D?
    /// Bumped by the "recenter on me" button. Coordinator diffs it against the last value it
    /// handled so a re-render without a new tap doesn't re-animate the camera.
    let recenterRequestId: Int
    /// A search result's coordinate to center on — distinct from `initialCameraTarget`/
    /// `recenterRequestId` (which only ever recenter to the user's own location).
    var focusTarget: CLLocationCoordinate2D? = nil
    /// Bumped whenever a new search result is selected, same diffing pattern as `recenterRequestId`.
    var focusRequestId: Int = 0
    /// A user-selected search result's marker, scaled up with **no dimming of the rest** — kept
    /// separate from `winnerPlaceId` since that means "MakanApa picked this for you," not
    /// "the user tapped a search result."
    var highlightedSearchPlaceId: Int? = nil
    /// A `.googleFallback` search result's coordinate before it resolves to a canonical
    /// restaurant/marker — rendered as a lightweight standalone pin.
    var temporarySearchCoordinate: CLLocationCoordinate2D? = nil
    /// Show all on map: one numbered pin per search result, in list order. Empty otherwise.
    var searchPins: [SearchPin] = []
    /// Bumped to fit the camera around the top few `searchPins`.
    var fitSearchPinsRequestId: Int = 0
    /// Pan (keeping the current zoom) to a result the user swiped to or tapped in map mode.
    var panTarget: CLLocationCoordinate2D? = nil
    var panRequestId: Int = 0
    /// The place whose sheet is open — kept out of clusters like the winner, so a place picked
    /// from the area panel's list is never hidden inside a count bubble.
    var selectedPlaceId: Int? = nil
    let onCameraIdle: (MapViewport, Float) -> Void
    let onMarkerTapped: (NearbyPlace) -> Void
    var onSearchPinTapped: (String) -> Void = { _ in }
    /// Fires just before a cluster tap moves the camera, so the view model can treat the move
    /// as the app's own and not raise "Search this area" (the places are already loaded).
    var onClusterCameraMove: () -> Void = {}

    func makeUIView(context: Context) -> GMSMapView {
        let options = GMSMapViewOptions()
        let mapView = GMSMapView(options: options)
        mapView.delegate = context.coordinator
        mapView.isMyLocationEnabled = true
        mapView.settings.myLocationButton = false
        // Google's logo/legal attribution sits pinned to this padding's bottom-left corner — the
        // Maps Platform ToS require it stay visible and unobscured, so this shifts it clear of
        // the "Around here" pill/sheet rather than hiding it (matches the 130pt the recenter
        // button in NearbyView clears the same UI by).
        mapView.padding = Self.browsePadding
        if let target = initialCameraTarget {
            mapView.camera = GMSCameraPosition(target: target, zoom: 15)
        }
        return mapView
    }

    func updateUIView(_ mapView: GMSMapView, context: Context) {
        if let target = initialCameraTarget, !context.coordinator.didSetInitialCamera {
            mapView.animate(to: GMSCameraPosition(target: target, zoom: 15))
            context.coordinator.didSetInitialCamera = true
        }

        if recenterRequestId != context.coordinator.lastHandledRecenterId, let target = initialCameraTarget {
            mapView.animate(to: GMSCameraPosition(target: target, zoom: 15))
            context.coordinator.lastHandledRecenterId = recenterRequestId
        }

        if focusRequestId != context.coordinator.lastHandledFocusId, let target = focusTarget {
            mapView.animate(to: GMSCameraPosition(target: target, zoom: max(mapView.camera.zoom, 16)))
            context.coordinator.lastHandledFocusId = focusRequestId
        }

        context.coordinator.sync(
            places: places, isPicking: isPicking, winnerPlaceId: winnerPlaceId,
            highlightedSearchPlaceId: highlightedSearchPlaceId, selectedPlaceId: selectedPlaceId, on: mapView
        )
        context.coordinator.syncTemporaryMarker(coordinate: temporarySearchCoordinate, on: mapView)
        context.coordinator.syncSearchPins(searchPins, on: mapView)

        // In map mode the search capsule covers the top and the carousel the bottom — shift the
        // map's own "center" into the clear band between them, so fitting and panning put pins
        // where the user can actually see them (Google's logo moves up with it, still visible).
        let padding = searchPins.isEmpty
            ? Self.browsePadding
            : UIEdgeInsets(top: 90, left: 0, bottom: 150, right: 0)
        if mapView.padding != padding {
            mapView.padding = padding
        }

        if fitSearchPinsRequestId != context.coordinator.lastHandledFitId, !searchPins.isEmpty {
            context.coordinator.lastHandledFitId = fitSearchPinsRequestId
            // Frame the best few results (plus whichever is selected) at a comfortable zoom,
            // not all of them — fitting a 6 km spread zooms out until pins pile up on each other.
            let focused = searchPins.filter { $0.rank <= Self.fitResultCount || $0.isSelected }
            if focused.count == 1, let only = focused.first {
                mapView.animate(to: GMSCameraPosition(target: only.coordinate, zoom: 16))
            } else {
                let bounds = focused.reduce(GMSCoordinateBounds()) { $0.includingCoordinate($1.coordinate) }
                if let fitted = mapView.camera(for: bounds, insets: UIEdgeInsets(top: 40, left: 44, bottom: 40, right: 44)) {
                    // A tight cluster shouldn't zoom in past street level.
                    mapView.animate(to: GMSCameraPosition(target: fitted.target, zoom: min(fitted.zoom, 17)))
                }
            }
        }

        if panRequestId != context.coordinator.lastHandledPanId, let target = panTarget {
            context.coordinator.lastHandledPanId = panRequestId
            mapView.animate(toLocation: target)
        }
    }

    /// The browse-mode padding set in makeUIView — Google's attribution clears the area panel.
    private static let browsePadding = UIEdgeInsets(top: 0, left: 0, bottom: 130, right: 0)
    /// How many of the top results Show all on map frames.
    private static let fitResultCount = 5

    func makeCoordinator() -> Coordinator {
        Coordinator(
            onCameraIdle: onCameraIdle, onMarkerTapped: onMarkerTapped,
            onSearchPinTapped: onSearchPinTapped, onClusterCameraMove: onClusterCameraMove
        )
    }

    @MainActor
    final class Coordinator: NSObject, @preconcurrency GMSMapViewDelegate {
        var didSetInitialCamera = false
        var lastHandledRecenterId = 0
        var lastHandledFocusId = 0
        var lastHandledFitId = 0
        var lastHandledPanId = 0

        private let onCameraIdle: (MapViewport, Float) -> Void
        private let onMarkerTapped: (NearbyPlace) -> Void
        private let onSearchPinTapped: (String) -> Void
        private let onClusterCameraMove: () -> Void
        private var searchPinMarkers: [String: GMSMarker] = [:]
        private var lastSearchPins: [SearchPin] = []
        private var markersById: [Int: GMSMarker] = [:]
        /// A marker sits in `markersById` at all times, but only gets an entry here — a live
        /// `UIView` swapped in via `marker.iconView` — while it's actually mid-transform (entrance
        /// pop, winner bounce, search-result highlight scale). `GMSMapView` repositions every
        /// `iconView` marker's `UIView` on every camera frame, which is cheap for one or two
        /// markers but visibly janks panning once dozens sit in that mode permanently — so a
        /// settled marker always drops back to the GPU-composited `marker.icon` bitmap instead
        /// (see `activateIconView`/`settle`).
        private var iconViewsById: [Int: UIImageView] = [:]
        private var lastWinnerId: Int?
        private var lastHighlightedSearchId: Int?
        private var pulseTimer: Timer?
        private var pulseDim = false
        private var temporarySearchMarker: GMSMarker?

        // MARK: Clustering state
        /// Last `places` / protected ids clustering ran against — `sync()` fires on every SwiftUI
        /// update, so it only reclusters when one of these actually changed.
        private var clusteredPlaces: [NearbyPlace] = []
        /// Winner, highlighted search result and open sheet — always standalone markers.
        private var protectedPlaceIds: Set<Int> = []
        private var winnerPlaceId: Int?
        /// Places currently folded into a count bubble (their markers are detached, not removed).
        private var clusteredPlaceIds: Set<Int> = []
        private var clusterMarkers: [String: GMSMarker] = [:]
        private var fan: Fan?

        /// A stacked cluster laid out as a ring of real pills around its spot, with legs back to it.
        private struct Fan {
            let zoom: Float
            let positions: [Int: CLLocationCoordinate2D]
            let legs: [GMSPolyline]
        }

        /// Zoom drift an open fan survives — past it the ring no longer matches pill spacing.
        private static let fanZoomTolerance: Float = 0.25
        private static let clusterZIndex: Int32 = 5
        private static let fannedZIndex: Int32 = 6

        init(
            onCameraIdle: @escaping (MapViewport, Float) -> Void,
            onMarkerTapped: @escaping (NearbyPlace) -> Void,
            onSearchPinTapped: @escaping (String) -> Void,
            onClusterCameraMove: @escaping () -> Void
        ) {
            self.onCameraIdle = onCameraIdle
            self.onMarkerTapped = onMarkerTapped
            self.onSearchPinTapped = onSearchPinTapped
            self.onClusterCameraMove = onClusterCameraMove
        }

        func mapView(_ mapView: GMSMapView, idleAt position: GMSCameraPosition) {
            applyClustering(on: mapView)
            let bounds = GMSCoordinateBounds(region: mapView.projection.visibleRegion())
            let viewport = MapViewport(
                north: bounds.northEast.latitude, south: bounds.southWest.latitude,
                east: bounds.northEast.longitude, west: bounds.southWest.longitude
            )
            onCameraIdle(viewport, position.zoom)
        }

        func mapView(_ mapView: GMSMapView, didTap marker: GMSMarker) -> Bool {
            if let pin = marker.userData as? SearchPin {
                onSearchPinTapped(pin.id)
                return true
            }
            if let cluster = marker.userData as? PlaceCluster {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                if cluster.isStacked {
                    expandFan(cluster, on: mapView)
                } else {
                    zoomInto(cluster, on: mapView)
                }
                return true
            }
            guard let place = marker.userData as? NearbyPlace else { return false }
            onMarkerTapped(place)
            return true
        }

        /// Tapping empty map folds an open fan back into its bubble.
        func mapView(_ mapView: GMSMapView, didTapAt coordinate: CLLocationCoordinate2D) {
            guard fan != nil else { return }
            collapseFan()
            applyClustering(on: mapView)
        }

        /// Numbered result pins for Show all on map — #1 in brand red, the selected one larger,
        /// the rest white. Diffed against the last set so an unchanged list doesn't re-render.
        func syncSearchPins(_ pins: [SearchPin], on mapView: GMSMapView) {
            guard pins != lastSearchPins else { return }
            lastSearchPins = pins

            let currentIds = Set(pins.map(\.id))
            for (id, marker) in searchPinMarkers where !currentIds.contains(id) {
                marker.map = nil
                searchPinMarkers[id] = nil
            }
            for pin in pins {
                let marker = searchPinMarkers[pin.id] ?? GMSMarker(position: pin.coordinate)
                marker.position = pin.coordinate
                marker.userData = pin
                marker.icon = SearchPinRenderer.icon(rank: pin.rank, isTop: pin.isTop, isSelected: pin.isSelected)
                marker.groundAnchor = CGPoint(x: 0.5, y: 0.5)
                marker.zIndex = pin.isSelected ? 30 : (pin.isTop ? 25 : 20)
                if marker.map == nil { marker.map = mapView }
                searchPinMarkers[pin.id] = marker
            }
        }

        func sync(
            places: [NearbyPlace], isPicking: Bool, winnerPlaceId: Int?,
            highlightedSearchPlaceId: Int?, selectedPlaceId: Int?, on mapView: GMSMapView
        ) {
            self.winnerPlaceId = winnerPlaceId
            let currentIds = Set(places.map(\.id))

            for (id, marker) in markersById where !currentIds.contains(id) {
                removeMarker(id: id, marker: marker)
            }

            var newIndex = 0
            for place in places {
                let isWinner = winnerPlaceId == place.id
                if let marker = markersById[place.id] {
                    marker.position = fan?.positions[place.id] ?? place.coordinate
                    marker.userData = place
                    applyIcon(id: place.id, marker: marker, rating: place.rating, isWinner: isWinner)
                    marker.zIndex = zIndex(for: place.id)
                    applyOpacity(marker, winnerPlaceId: winnerPlaceId, placeId: place.id)
                } else {
                    addMarker(for: place, winnerPlaceId: winnerPlaceId, staggerIndex: newIndex, on: mapView)
                    newIndex += 1
                }
            }

            // Before the winner bounce / search highlight below — both need their marker out of
            // any bubble, and protecting them here is what brings it back.
            let protectedIds = Set([winnerPlaceId, highlightedSearchPlaceId, selectedPlaceId].compactMap { $0 })
            if places != clusteredPlaces || protectedIds != protectedPlaceIds {
                clusteredPlaces = places
                protectedPlaceIds = protectedIds
                applyClustering(on: mapView)
            }
            for marker in clusterMarkers.values {
                marker.opacity = winnerPlaceId == nil ? 1.0 : 0.35
            }

            if let winnerPlaceId, winnerPlaceId != lastWinnerId, let marker = markersById[winnerPlaceId] {
                let image = RatingBubbleRenderer.icon(rating: places.first { $0.id == winnerPlaceId }?.rating, isWinner: true)
                let iconView = activateIconView(id: winnerPlaceId, marker: marker, image: image)
                bounce(iconView) { [weak self] in self?.settle(id: winnerPlaceId, marker: marker) }
            }
            lastWinnerId = winnerPlaceId

            if isPicking {
                startPulse()
            } else {
                stopPulse()
            }

            syncSearchHighlight(highlightedSearchPlaceId)
        }

        /// Scales the selected search result's pin up (1.10×) and back down on change — no
        /// opacity dimming of the rest, unlike `winnerPlaceId`. A user picking a search result
        /// shouldn't read as "MakanApa recommends this one."
        private func syncSearchHighlight(_ highlightedSearchPlaceId: Int?) {
            guard highlightedSearchPlaceId != lastHighlightedSearchId else { return }

            if let previousId = lastHighlightedSearchId, let marker = markersById[previousId],
               let iconView = iconViewsById[previousId] {
                UIView.animate(withDuration: 0.16, animations: {
                    iconView.transform = .identity
                }, completion: { [weak self] _ in
                    self?.settle(id: previousId, marker: marker)
                })
            }
            if let newId = highlightedSearchPlaceId, let marker = markersById[newId] {
                let image = RatingBubbleRenderer.icon(rating: (marker.userData as? NearbyPlace)?.rating, isWinner: lastWinnerId == newId)
                let iconView = activateIconView(id: newId, marker: marker, image: image)
                UIView.animate(
                    withDuration: 0.2, delay: 0, usingSpringWithDamping: 0.6, initialSpringVelocity: 0.4, options: []
                ) {
                    iconView.transform = CGAffineTransform(scaleX: 1.10, y: 1.10)
                }
            }
            lastHighlightedSearchId = highlightedSearchPlaceId
        }

        /// A `.googleFallback` search result isn't in `places` yet (no `restaurant_id`), so it
        /// gets its own lightweight marker at the result's coordinate until `resolvePlace`
        /// returns a canonical restaurant and the normal marker path takes over.
        func syncTemporaryMarker(coordinate: CLLocationCoordinate2D?, on mapView: GMSMapView) {
            guard let coordinate else {
                temporarySearchMarker?.map = nil
                temporarySearchMarker = nil
                return
            }
            if let marker = temporarySearchMarker {
                marker.position = coordinate
            } else {
                let marker = GMSMarker(position: coordinate)
                // Not routed through RatingBubbleRenderer.icon — that path is now invisible for
                // ratingless places (declutter), but this pin is a deliberate "you picked this
                // spot" marker, not restaurant clutter, so it always needs to actually show.
                marker.icon = GMSMarker.markerImage(with: UIColor(named: "SambalRed") ?? .systemRed)
                marker.zIndex = 20
                marker.map = mapView
                temporarySearchMarker = marker
            }
        }

        // MARK: - Marker lifecycle

        private func addMarker(for place: NearbyPlace, winnerPlaceId: Int?, staggerIndex: Int, on mapView: GMSMapView) {
            let isWinner = winnerPlaceId == place.id
            let image = RatingBubbleRenderer.icon(rating: place.rating, isWinner: isWinner)

            let marker = GMSMarker(position: place.coordinate)
            marker.userData = place
            marker.zIndex = zIndex(for: place.id)
            marker.map = mapView
            markersById[place.id] = marker
            applyOpacity(marker, winnerPlaceId: winnerPlaceId, placeId: place.id)

            let iconView = activateIconView(id: place.id, marker: marker, image: image)
            iconView.alpha = 0
            iconView.transform = CGAffineTransform(scaleX: 0.85, y: 0.85)

            // Several markers can land in the same `sync()` (first load, "search this area") —
            // a small per-marker delay reads as a stagger instead of everything popping at once.
            let delay = Double(min(staggerIndex, 8)) * 0.03
            UIView.animate(
                withDuration: 0.28, delay: delay, usingSpringWithDamping: 0.7, initialSpringVelocity: 0.4,
                options: [.allowUserInteraction],
                animations: {
                    iconView.alpha = 1
                    iconView.transform = .identity
                }, completion: { [weak self] _ in
                    self?.settle(id: place.id, marker: marker)
                }
            )
        }

        private func removeMarker(id: Int, marker: GMSMarker) {
            markersById.removeValue(forKey: id)
            // Already detached inside a cluster — nothing on screen to animate out.
            guard marker.map != nil else { return }
            let iconView = iconViewsById[id] ?? activateIconView(id: id, marker: marker, image: marker.icon ?? RatingBubbleRenderer.icon(rating: nil, isWinner: false))
            UIView.animate(withDuration: 0.18, animations: {
                iconView.alpha = 0
                iconView.transform = CGAffineTransform(scaleX: 0.6, y: 0.6)
            }, completion: { [weak self] _ in
                self?.iconViewsById.removeValue(forKey: id)
                marker.map = nil
            })
        }

        /// Live (`iconView`) markers get their bitmap updated in place; settled markers just get
        /// a fresh `marker.icon` bitmap swapped in — either way avoids re-triggering the entrance
        /// animation or promoting a settled marker back to a live `UIView` just to redraw a rating.
        private func applyIcon(id: Int, marker: GMSMarker, rating: Double?, isWinner: Bool) {
            let image = RatingBubbleRenderer.icon(rating: rating, isWinner: isWinner)
            if let iconView = iconViewsById[id] {
                iconView.image = image
                iconView.bounds.size = image.size
            } else {
                marker.icon = image
            }
        }

        /// Promotes a settled (`icon`-only) marker to a live `iconView` so it can be transform-
        /// animated, or returns its existing live view unchanged if it's already live.
        @discardableResult
        private func activateIconView(id: Int, marker: GMSMarker, image: UIImage) -> UIImageView {
            if let existing = iconViewsById[id] {
                return existing
            }
            let iconView = UIImageView(image: image)
            iconView.frame = CGRect(origin: .zero, size: image.size)
            marker.icon = nil
            marker.iconView = iconView
            iconViewsById[id] = iconView
            return iconView
        }

        /// Demotes a live marker back to a static `icon` bitmap once its transform has returned
        /// to identity — a marker mid-transform (e.g. still at highlight scale) is left alone so
        /// this never clips an animation partway through.
        private func settle(id: Int, marker: GMSMarker) {
            guard let iconView = iconViewsById[id], iconView.transform == .identity else { return }
            marker.iconView = nil
            marker.icon = iconView.image
            iconViewsById.removeValue(forKey: id)
        }

        // MARK: - Clustering

        /// Folds rating pills that would overlap into count bubbles. Runs on every camera idle and
        /// whenever the places / protected set change — never per camera frame, so a pinch can
        /// overlap pills for a moment until the camera settles. A pure pan yields the same groups
        /// (same ids, same markers reused), so reclustering on it changes nothing on screen.
        private func applyClustering(on mapView: GMSMapView) {
            let zoom = mapView.camera.zoom
            if let fan, abs(fan.zoom - zoom) >= Self.fanZoomTolerance
                || !fan.positions.keys.allSatisfy({ markersById[$0] != nil }) {
                collapseFan()
            }
            // Before first layout the projection maps every place onto one point — everything
            // would read as stacked. Leave pills as they are; the first idle reclusters.
            guard mapView.bounds.width > 0, mapView.bounds.height > 0 else { return }

            let fannedIds: Set<Int> = fan.map { Set($0.positions.keys) } ?? []
            // Unrated places are invisible markers (see RatingBubbleRenderer) — a bubble counting
            // them would surface exactly the clutter they're hidden to avoid.
            let inputs = clusteredPlaces.compactMap { place -> ClusterInput? in
                guard let rating = place.rating, !protectedPlaceIds.contains(place.id),
                      !fannedIds.contains(place.id) else { return nil }
                return ClusterInput(
                    placeId: place.id, rating: rating,
                    point: mapView.projection.point(for: place.coordinate),
                    size: RatingBubbleRenderer.icon(rating: rating, isWinner: false).size
                )
            }
            let clusters = MarkerClusterer.clusters(inputs, zoom: zoom) { ClusterBubbleRenderer.icon(count: $0).size }

            clusteredPlaceIds = Set(clusters.flatMap(\.memberIds))
            for id in markersById.keys {
                setPlaceMarker(id: id, visible: !clusteredPlaceIds.contains(id), on: mapView)
            }

            let liveClusterIds = Set(clusters.map(\.id))
            for (id, marker) in clusterMarkers where !liveClusterIds.contains(id) {
                marker.map = nil
                clusterMarkers[id] = nil
            }
            for cluster in clusters {
                let marker = clusterMarkers[cluster.id] ?? GMSMarker()
                marker.position = mapView.projection.coordinate(for: cluster.point)
                marker.userData = cluster
                let icon = ClusterBubbleRenderer.icon(count: cluster.memberIds.count)
                if marker.icon !== icon {
                    marker.icon = icon
                }
                // Google uses the title as the marker's VoiceOver label; didTap returning true
                // keeps it from ever opening an info window.
                marker.title = "\(cluster.memberIds.count) places here"
                marker.zIndex = Self.clusterZIndex
                marker.opacity = winnerPlaceId == nil ? 1.0 : 0.35
                if marker.map == nil {
                    marker.appearAnimation = .pop
                    marker.map = mapView
                }
                clusterMarkers[cluster.id] = marker
            }
        }

        /// The one place that hides or re-shows a place marker for clustering. Hiding a marker
        /// mid-animation (entrance pop, winner bounce) first drops it back to a static bitmap so no
        /// stale live `iconView` outlives it; re-showing pops it in, so a split reads as the
        /// bubble breaking apart.
        private func setPlaceMarker(id: Int, visible: Bool, on mapView: GMSMapView) {
            guard let marker = markersById[id] else { return }
            if visible {
                guard marker.map == nil else { return }
                marker.appearAnimation = .pop
                marker.map = mapView
            } else {
                guard marker.map != nil else { return }
                if let iconView = iconViewsById.removeValue(forKey: id) {
                    iconView.layer.removeAllAnimations()
                    marker.iconView = nil
                    marker.icon = iconView.image
                }
                marker.map = nil
            }
        }

        /// Frames just this cluster's places — at least one zoom level closer so a tap always
        /// makes progress, never past `maxExpansionZoom` (a group that still collides there is
        /// stacked and fans out instead).
        private func zoomInto(_ cluster: PlaceCluster, on mapView: GMSMapView) {
            let bounds = cluster.memberIds
                .compactMap { markersById[$0]?.userData as? NearbyPlace }
                .reduce(GMSCoordinateBounds()) { $0.includingCoordinate($1.coordinate) }
            guard let fitted = mapView.camera(for: bounds, insets: UIEdgeInsets(top: 60, left: 60, bottom: 60, right: 60)) else { return }
            let zoom = min(max(fitted.zoom, mapView.camera.zoom + 1), MarkerClusterer.maxExpansionZoom)
            onClusterCameraMove()
            mapView.animate(to: GMSCameraPosition(target: fitted.target, zoom: zoom))
        }

        /// Lays a stacked cluster's pills out in rings around its spot, each with a thin leg back
        /// to where it really is. Stays open while the user taps through them (each opens its
        /// sheet); folds back on a map tap or once the zoom moves.
        private func expandFan(_ cluster: PlaceCluster, on mapView: GMSMapView) {
            // One fan at a time — fold the open one back into its bubble first.
            if fan != nil {
                collapseFan()
                applyClustering(on: mapView)
            }
            guard let clusterMarker = clusterMarkers.removeValue(forKey: cluster.id),
                  let current = clusterMarker.userData as? PlaceCluster else { return }
            clusterMarker.map = nil

            let anchor = mapView.projection.point(for: clusterMarker.position)
            let members = current.memberIds.compactMap { markersById[$0]?.userData as? NearbyPlace }
            let sizes = members.map { RatingBubbleRenderer.icon(rating: $0.rating, isWinner: false).size }
            let offsets = MarkerClusterer.fanOffsets(for: sizes)
            let legColor = (UIColor(named: "Kicap") ?? .darkText).withAlphaComponent(0.45)

            var positions: [Int: CLLocationCoordinate2D] = [:]
            var legs: [GMSPolyline] = []
            for (place, (offset, size)) in zip(members, zip(offsets, sizes)) {
                // Pills are bottom-anchored — drop the anchor half a pill so the pill's centre
                // sits on the ring.
                let position = mapView.projection.coordinate(
                    for: CGPoint(x: anchor.x + offset.x, y: anchor.y + offset.y + size.height / 2)
                )
                positions[place.id] = position
                markersById[place.id]?.position = position
                markersById[place.id]?.zIndex = Self.fannedZIndex
                setPlaceMarker(id: place.id, visible: true, on: mapView)

                let path = GMSMutablePath()
                path.add(place.coordinate)
                path.add(position)
                let leg = GMSPolyline(path: path)
                leg.strokeWidth = 1.5
                leg.strokeColor = legColor
                leg.map = mapView
                legs.append(leg)
            }
            clusteredPlaceIds.subtract(positions.keys)
            fan = Fan(zoom: mapView.camera.zoom, positions: positions, legs: legs)

            onClusterCameraMove()
            mapView.animate(toLocation: clusterMarker.position)
        }

        /// Puts fanned pills back on their real coordinates and drops the legs. Callers recluster
        /// straight after, which folds them back into their bubble.
        private func collapseFan() {
            guard let fan else { return }
            self.fan = nil
            fan.legs.forEach { $0.map = nil }
            for id in fan.positions.keys {
                guard let marker = markersById[id], let place = marker.userData as? NearbyPlace else { continue }
                marker.position = place.coordinate
                marker.zIndex = zIndex(for: id)
            }
        }

        private func zIndex(for placeId: Int) -> Int32 {
            if winnerPlaceId == placeId { return 10 }
            return fan?.positions[placeId] != nil ? Self.fannedZIndex : 0
        }

        private func applyOpacity(_ marker: GMSMarker, winnerPlaceId: Int?, placeId: Int) {
            if winnerPlaceId != nil {
                marker.opacity = winnerPlaceId == placeId ? 1.0 : 0.35
            } else {
                marker.opacity = 1.0
            }
        }

        /// One-shot pop, not a loop — the winner should feel like it just got tapped on the
        /// shoulder, not keep vibrating for as long as the sheet is open. `onSettled` runs after
        /// the transform returns to identity, so the caller (`sync()`) can drop this marker back
        /// out of live `iconView` mode once the animation's done with it.
        private func bounce(_ iconView: UIView, onSettled: @escaping () -> Void) {
            UIView.animate(withDuration: 0.14, animations: {
                iconView.transform = CGAffineTransform(scaleX: 1.16, y: 1.16)
            }, completion: { _ in
                UIView.animate(
                    withDuration: 0.16, delay: 0, usingSpringWithDamping: 0.5, initialSpringVelocity: 0.6, options: [],
                    animations: { iconView.transform = .identity },
                    completion: { _ in onSettled() }
                )
            })
        }

        private func startPulse() {
            guard pulseTimer == nil else { return }
            pulseTimer = Timer.scheduledTimer(withTimeInterval: 0.22, repeats: true) { [weak self] _ in
                guard let self else { return }
                self.pulseDim.toggle()
                for marker in Array(self.markersById.values) + Array(self.clusterMarkers.values) {
                    marker.opacity = self.pulseDim ? 0.5 : 1.0
                }
            }
        }

        /// Only stops the timer — `sync()` above already set each marker's correct steady-state
        /// opacity (dimmed loser / full-opacity winner / plain 1.0) before calling this, so
        /// resetting opacity here would stomp that.
        private func stopPulse() {
            pulseTimer?.invalidate()
            pulseTimer = nil
            pulseDim = false
        }
    }
}

/// Renders a marker icon as a `UIImage` — plain UIKit rasterizer since it's assigned to a
/// `UIImageView` used as the marker's `iconView`. Two visual tiers: **winner** (sambal-red
/// `★4.7` pill, "Pick one lah" result) > **any rated place** (white `★4.7` pill). A place with
/// no rating at all is a plain (invisible) dot — there's no meaningful "★–" state to show.
@MainActor
enum RatingBubbleRenderer {
    /// Invisible, not just small — an unrated place gets no visible mark on the map (still
    /// tappable: Google Maps' own base-layer POI icon is what a user actually taps for these).
    /// Kept as a real (if invisible) icon rather than omitting the marker entirely, so
    /// tap-to-select still works even for places with no rating.
    private static let invisibleMarkerDiameter: CGFloat = 24

    /// Keyspace is tiny by construction (one-decimal ratings 0.0-5.0 × winner true/false), so a
    /// plain dictionary with no eviction policy is fine — never needs the ceremony `NSCache`
    /// would add. Avoids re-rasterizing the same bitmap on every `addMarker`/`applyIcon` call,
    /// which fires on every `sync()` even for markers whose (rating, isWinner) hasn't changed.
    private struct RatingMarkerKey: Hashable {
        let ratingTenths: Int?
        let isWinner: Bool
    }

    private static var cache: [RatingMarkerKey: UIImage] = [:]

    static func icon(rating: Double?, isWinner: Bool) -> UIImage {
        let key = RatingMarkerKey(ratingTenths: rating.map { Int(($0 * 10).rounded()) }, isWinner: isWinner)
        if let cached = cache[key] {
            return cached
        }

        let image = rating.map { pillIcon(rating: $0, highlighted: isWinner) } ?? invisibleIcon()
        cache[key] = image
        return image
    }

    private static func invisibleIcon() -> UIImage {
        let size = CGSize(width: invisibleMarkerDiameter, height: invisibleMarkerDiameter)
        let renderer = UIGraphicsImageRenderer(size: size)
        return renderer.image { _ in }
    }

    private static func pillIcon(rating: Double, highlighted: Bool) -> UIImage {
        let text = String(format: "★%.1f", rating)
        let font = UIFont.systemFont(ofSize: highlighted ? 15 : 12, weight: .bold)
        let padding: CGFloat = highlighted ? 10 : 7
        let textSize = (text as NSString).size(withAttributes: [.font: font])
        let size = CGSize(width: textSize.width + padding * 2, height: textSize.height + padding)

        let renderer = UIGraphicsImageRenderer(size: size)
        return renderer.image { context in
            let backgroundColor = highlighted ? UIColor(named: "SambalRed") ?? .systemRed : UIColor.white
            let textColor = highlighted ? UIColor.white : UIColor(named: "Kicap") ?? .darkText

            let path = UIBezierPath(roundedRect: CGRect(origin: .zero, size: size), cornerRadius: size.height / 2)
            backgroundColor.setFill()
            path.fill()

            context.cgContext.setShadow(offset: CGSize(width: 0, height: 1), blur: 2, color: UIColor.black.withAlphaComponent(0.15).cgColor)

            (text as NSString).draw(
                at: CGPoint(x: padding, y: padding / 2),
                withAttributes: [.font: font, .foregroundColor: textColor as Any]
            )
        }
    }
}


/// The count bubble a group of overlapping pills folds into — soy-dark rather than white so it
/// never reads as one more rating pill, and never red so it can't pass for a "Pick one lah" winner.
@MainActor
enum ClusterBubbleRenderer {
    private static var cache: [Int: UIImage] = [:]

    static func icon(count: Int) -> UIImage {
        if let cached = cache[count] { return cached }

        let text = "\(count)" as NSString
        let attributes: [NSAttributedString.Key: Any] = [
            .font: UIFont.systemFont(ofSize: 13, weight: .heavy), .foregroundColor: UIColor.white,
        ]
        let textSize = text.size(withAttributes: attributes)
        let ring: CGFloat = 2
        let shadowInset: CGFloat = 2
        // A circle up to two digits, stretching to a capsule past that.
        let bubble = CGSize(width: max(30, textSize.width + 18), height: 30)
        let size = CGSize(width: bubble.width + shadowInset * 2, height: bubble.height + shadowInset * 2)

        let image = UIGraphicsImageRenderer(size: size).image { context in
            let rect = CGRect(origin: CGPoint(x: shadowInset, y: shadowInset), size: bubble)
            context.cgContext.setShadow(offset: CGSize(width: 0, height: 1), blur: 3, color: UIColor.black.withAlphaComponent(0.25).cgColor)
            UIColor.white.setFill()
            UIBezierPath(roundedRect: rect, cornerRadius: rect.height / 2).fill()
            context.cgContext.setShadow(offset: .zero, blur: 0, color: nil)
            let inner = rect.insetBy(dx: ring, dy: ring)
            (UIColor(named: "Kicap") ?? .darkText).setFill()
            UIBezierPath(roundedRect: inner, cornerRadius: inner.height / 2).fill()
            text.draw(at: CGPoint(x: rect.midX - textSize.width / 2, y: rect.midY - textSize.height / 2), withAttributes: attributes)
        }
        cache[count] = image
        return image
    }
}

private extension NearbyPlace {
    var coordinate: CLLocationCoordinate2D {
        CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
    }
}

/// One search result on the map in Show all on map.
struct SearchPin: Equatable {
    let id: String
    let rank: Int
    let coordinate: CLLocationCoordinate2D
    let isTop: Bool
    let isSelected: Bool

    static func == (lhs: SearchPin, rhs: SearchPin) -> Bool {
        lhs.id == rhs.id && lhs.rank == rhs.rank && lhs.isTop == rhs.isTop && lhs.isSelected == rhs.isSelected
            && lhs.coordinate.latitude == rhs.coordinate.latitude && lhs.coordinate.longitude == rhs.coordinate.longitude
    }
}

/// Round numbered badges for result pins — rank order matches the list and the carousel.
@MainActor
enum SearchPinRenderer {
    private struct Key: Hashable {
        let rank: Int
        let isTop: Bool
        let isSelected: Bool
    }

    private static var cache: [Key: UIImage] = [:]

    static func icon(rank: Int, isTop: Bool, isSelected: Bool) -> UIImage {
        let key = Key(rank: rank, isTop: isTop, isSelected: isSelected)
        if let cached = cache[key] { return cached }

        let diameter: CGFloat = isSelected ? 40 : 28
        let ring: CGFloat = isSelected ? 4 : 2
        let sambal = UIColor(named: "SambalRed") ?? .systemRed
        let kicap = UIColor(named: "Kicap") ?? .darkText
        let fill = isTop || isSelected ? sambal : UIColor.white
        let textColor = isTop || isSelected ? UIColor.white : kicap
        let font = UIFont.systemFont(ofSize: isSelected ? 17 : 12, weight: .heavy)
        let size = CGSize(width: diameter + 4, height: diameter + 4)

        let image = UIGraphicsImageRenderer(size: size).image { context in
            let circle = CGRect(x: 2, y: 2, width: diameter, height: diameter)
            context.cgContext.setShadow(offset: CGSize(width: 0, height: 1), blur: 3, color: UIColor.black.withAlphaComponent(0.25).cgColor)
            UIColor.white.setFill()
            UIBezierPath(ovalIn: circle).fill()
            context.cgContext.setShadow(offset: .zero, blur: 0, color: nil)
            fill.setFill()
            UIBezierPath(ovalIn: circle.insetBy(dx: ring, dy: ring)).fill()

            let text = "\(rank)" as NSString
            let attributes: [NSAttributedString.Key: Any] = [.font: font, .foregroundColor: textColor]
            let textSize = text.size(withAttributes: attributes)
            text.draw(at: CGPoint(x: circle.midX - textSize.width / 2, y: circle.midY - textSize.height / 2), withAttributes: attributes)
        }
        cache[key] = image
        return image
    }
}
