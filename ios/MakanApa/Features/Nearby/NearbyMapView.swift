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

        context.coordinator.sync(places: places, isPicking: isPicking, winnerPlaceId: winnerPlaceId, on: mapView)
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(onCameraIdle: onCameraIdle, onMarkerTapped: onMarkerTapped)
    }

    @MainActor
    final class Coordinator: NSObject, @preconcurrency GMSMapViewDelegate {
        var didSetInitialCamera = false
        var lastHandledRecenterId = 0

        private let onCameraIdle: (MapViewport, Float) -> Void
        private let onMarkerTapped: (NearbyPlace) -> Void
        private var markersById: [Int: GMSMarker] = [:]
        /// Kept alongside `markersById` since a `GMSMarker` using `iconView` (instead of the
        /// static `icon` image) needs a live `UIView` reference to animate — that's what makes
        /// appear/bounce/removal transforms possible instead of just swapping a bitmap.
        private var iconViewsById: [Int: UIImageView] = [:]
        private var lastWinnerId: Int?
        private var pulseTimer: Timer?
        private var pulseDim = false

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

        func sync(places: [NearbyPlace], isPicking: Bool, winnerPlaceId: Int?, on mapView: GMSMapView) {
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
