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
    let onCameraIdle: (MapViewport, Float) -> Void
    let onMarkerTapped: (NearbyPlace) -> Void

    func makeUIView(context: Context) -> GMSMapView {
        let options = GMSMapViewOptions()
        let mapView = GMSMapView(options: options)
        mapView.delegate = context.coordinator
        mapView.isMyLocationEnabled = true
        mapView.settings.myLocationButton = false
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
            highlightedSearchPlaceId: highlightedSearchPlaceId, on: mapView
        )
        context.coordinator.syncTemporaryMarker(coordinate: temporarySearchCoordinate, on: mapView)
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(onCameraIdle: onCameraIdle, onMarkerTapped: onMarkerTapped)
    }

    @MainActor
    final class Coordinator: NSObject, @preconcurrency GMSMapViewDelegate {
        var didSetInitialCamera = false
        var lastHandledRecenterId = 0
        var lastHandledFocusId = 0

        private let onCameraIdle: (MapViewport, Float) -> Void
        private let onMarkerTapped: (NearbyPlace) -> Void
        private var markersById: [Int: GMSMarker] = [:]
        /// Kept alongside `markersById` since a `GMSMarker` using `iconView` (instead of the
        /// static `icon` image) needs a live `UIView` reference to animate — that's what makes
        /// appear/bounce/removal transforms possible instead of just swapping a bitmap.
        private var iconViewsById: [Int: UIImageView] = [:]
        private var lastWinnerId: Int?
        private var lastHighlightedSearchId: Int?
        private var pulseTimer: Timer?
        private var pulseDim = false
        private var temporarySearchMarker: GMSMarker?

        init(onCameraIdle: @escaping (MapViewport, Float) -> Void, onMarkerTapped: @escaping (NearbyPlace) -> Void) {
            self.onCameraIdle = onCameraIdle
            self.onMarkerTapped = onMarkerTapped
        }

        func mapView(_ mapView: GMSMapView, idleAt position: GMSCameraPosition) {
            let bounds = GMSCoordinateBounds(region: mapView.projection.visibleRegion())
            let viewport = MapViewport(
                north: bounds.northEast.latitude, south: bounds.southWest.latitude,
                east: bounds.northEast.longitude, west: bounds.southWest.longitude
            )
            onCameraIdle(viewport, position.zoom)
        }

        func mapView(_ mapView: GMSMapView, didTap marker: GMSMarker) -> Bool {
            guard let place = marker.userData as? NearbyPlace else { return false }
            onMarkerTapped(place)
            return true
        }

        func sync(
            places: [NearbyPlace], isPicking: Bool, winnerPlaceId: Int?,
            highlightedSearchPlaceId: Int?, on mapView: GMSMapView
        ) {
            let currentIds = Set(places.map(\.id))

            for (id, marker) in markersById where !currentIds.contains(id) {
                removeMarker(id: id, marker: marker)
            }

            var newIndex = 0
            for place in places {
                if let marker = markersById[place.id], let iconView = iconViewsById[place.id] {
                    marker.position = CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude)
                    marker.userData = place
                    applyIcon(to: iconView, rating: place.rating, highlighted: winnerPlaceId == place.id)
                    marker.zIndex = winnerPlaceId == place.id ? 10 : 0
                    applyOpacity(marker, winnerPlaceId: winnerPlaceId, placeId: place.id)
                } else {
                    addMarker(for: place, winnerPlaceId: winnerPlaceId, staggerIndex: newIndex, on: mapView)
                    newIndex += 1
                }
            }

            if let winnerPlaceId, winnerPlaceId != lastWinnerId, let iconView = iconViewsById[winnerPlaceId] {
                bounce(iconView)
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

            if let previousId = lastHighlightedSearchId, let iconView = iconViewsById[previousId] {
                UIView.animate(withDuration: 0.16) { iconView.transform = .identity }
            }
            if let newId = highlightedSearchPlaceId, let iconView = iconViewsById[newId] {
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
                marker.icon = RatingBubbleRenderer.icon(rating: nil, highlighted: true)
                marker.zIndex = 20
                marker.map = mapView
                temporarySearchMarker = marker
            }
        }

        // MARK: - Marker lifecycle

        private func addMarker(for place: NearbyPlace, winnerPlaceId: Int?, staggerIndex: Int, on mapView: GMSMapView) {
            let isWinner = winnerPlaceId == place.id
            let image = RatingBubbleRenderer.icon(rating: place.rating, highlighted: isWinner)
            let iconView = UIImageView(image: image)
            iconView.frame = CGRect(origin: .zero, size: image.size)
            iconView.alpha = 0
            iconView.transform = CGAffineTransform(scaleX: 0.85, y: 0.85)

            let marker = GMSMarker(position: CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude))
            marker.userData = place
            marker.iconView = iconView
            marker.zIndex = isWinner ? 10 : 0
            marker.map = mapView

            markersById[place.id] = marker
            iconViewsById[place.id] = iconView
            applyOpacity(marker, winnerPlaceId: winnerPlaceId, placeId: place.id)

            // Several markers can land in the same `sync()` (first load, "search this area") —
            // a small per-marker delay reads as a stagger instead of everything popping at once.
            let delay = Double(min(staggerIndex, 8)) * 0.03
            UIView.animate(
                withDuration: 0.28, delay: delay, usingSpringWithDamping: 0.7, initialSpringVelocity: 0.4,
                options: [.allowUserInteraction]
            ) {
                iconView.alpha = 1
                iconView.transform = .identity
            }
        }

        private func removeMarker(id: Int, marker: GMSMarker) {
            markersById.removeValue(forKey: id)
            guard let iconView = iconViewsById.removeValue(forKey: id) else {
                marker.map = nil
                return
            }
            UIView.animate(withDuration: 0.18, animations: {
                iconView.alpha = 0
                iconView.transform = CGAffineTransform(scaleX: 0.6, y: 0.6)
            }, completion: { _ in
                marker.map = nil
            })
        }

        private func applyIcon(to iconView: UIImageView, rating: Double?, highlighted: Bool) {
            let image = RatingBubbleRenderer.icon(rating: rating, highlighted: highlighted)
            iconView.image = image
            iconView.bounds.size = image.size
        }

        private func applyOpacity(_ marker: GMSMarker, winnerPlaceId: Int?, placeId: Int) {
            if winnerPlaceId != nil {
                marker.opacity = winnerPlaceId == placeId ? 1.0 : 0.35
            } else {
                marker.opacity = 1.0
            }
        }

        /// One-shot pop, not a loop — the winner should feel like it just got tapped on the
        /// shoulder, not keep vibrating for as long as the sheet is open.
        private func bounce(_ iconView: UIView) {
            UIView.animate(withDuration: 0.14, animations: {
                iconView.transform = CGAffineTransform(scaleX: 1.16, y: 1.16)
            }, completion: { _ in
                UIView.animate(withDuration: 0.16, delay: 0, usingSpringWithDamping: 0.5, initialSpringVelocity: 0.6, options: []) {
                    iconView.transform = .identity
                }
            })
        }

        private func startPulse() {
            guard pulseTimer == nil else { return }
            pulseTimer = Timer.scheduledTimer(withTimeInterval: 0.22, repeats: true) { [weak self] _ in
                guard let self else { return }
                self.pulseDim.toggle()
                for marker in self.markersById.values {
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

/// Renders the branded `★4.7` bubble marker as a `UIImage` — small by default, enlarged and
/// sambal-red when highlighted (the "Pick one lah" winner). Kept a plain UIKit rasterizer
/// since it's assigned to a `UIImageView` used as the marker's `iconView`.
enum RatingBubbleRenderer {
    static func icon(rating: Double?, highlighted: Bool) -> UIImage {
        let text = rating.map { String(format: "★%.1f", $0) } ?? "★–"
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
