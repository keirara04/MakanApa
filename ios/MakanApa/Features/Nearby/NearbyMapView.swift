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

        context.coordinator.sync(places: places, isPicking: isPicking, winnerPlaceId: winnerPlaceId, on: mapView)
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(onCameraIdle: onCameraIdle, onMarkerTapped: onMarkerTapped)
    }

    final class Coordinator: NSObject, GMSMapViewDelegate {
        var didSetInitialCamera = false

        private let onCameraIdle: (MapViewport, Float) -> Void
        private let onMarkerTapped: (NearbyPlace) -> Void
        private var markersById: [Int: GMSMarker] = [:]
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
                marker.map = nil
                markersById.removeValue(forKey: id)
            }

            for place in places {
                let marker = markersById[place.id] ?? {
                    let marker = GMSMarker()
                    marker.map = mapView
                    markersById[place.id] = marker
                    return marker
                }()
                marker.position = CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude)
                marker.userData = place

                let isWinner = winnerPlaceId == place.id
                marker.icon = RatingBubbleRenderer.icon(rating: place.rating, highlighted: isWinner)
                marker.zIndex = isWinner ? 10 : 0
                if winnerPlaceId != nil {
                    marker.opacity = isWinner ? 1.0 : 0.35
                } else {
                    marker.opacity = 1.0
                }
            }

            if isPicking {
                startPulse()
            } else {
                stopPulse()
            }
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
/// since `GMSMarker.icon` takes a `UIImage`, not a SwiftUI view.
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
