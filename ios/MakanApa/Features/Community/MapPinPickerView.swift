import CoreLocation
import MapKit
import SwiftUI

/// Fixed center pin while the map pans underneath it (standard "drag map, not pin" pattern) —
/// coordinates are authoritative, reverse-geocoded address is a friendly preview only and stays
/// optional if geocoding fails.
struct MapPinPickerView: View {
    let initialCoordinate: CLLocationCoordinate2D?
    let onConfirm: (CLLocationCoordinate2D) -> Void

    @Environment(LocationService.self) private var locationService
    @Environment(\.dismiss) private var dismiss
    @State private var cameraPosition: MapCameraPosition
    @State private var centerCoordinate: CLLocationCoordinate2D
    @State private var addressPreview: String?

    init(initialCoordinate: CLLocationCoordinate2D?, onConfirm: @escaping (CLLocationCoordinate2D) -> Void) {
        self.initialCoordinate = initialCoordinate
        self.onConfirm = onConfirm
        let start = initialCoordinate ?? CLLocationCoordinate2D(latitude: 3.1390, longitude: 101.6869)
        _cameraPosition = State(initialValue: .region(MKCoordinateRegion(center: start, span: MKCoordinateSpan(latitudeDelta: 0.01, longitudeDelta: 0.01))))
        _centerCoordinate = State(initialValue: start)
    }

    var body: some View {
        NavigationStack {
            ZStack {
                Map(position: $cameraPosition)
                    .onMapCameraChange(frequency: .continuous) { context in
                        centerCoordinate = context.region.center
                    }
                    .onMapCameraChange(frequency: .onEnd) { context in
                        Task { await reverseGeocode(context.region.center) }
                    }
                    .ignoresSafeArea(edges: .bottom)

                Image(systemName: "mappin")
                    .font(.system(size: 32))
                    .foregroundStyle(Color.sambalRed)
                    .offset(y: -16)

                VStack {
                    Spacer()
                    VStack(spacing: 12) {
                        if let addressPreview {
                            Text(addressPreview)
                                .font(.makanBody(13))
                                .foregroundStyle(.secondary)
                                .padding(.horizontal, 16)
                                .padding(.vertical, 8)
                                .background(.white)
                                .clipShape(Capsule())
                        }
                        Button(Copy.communityConfirmLocation) {
                            UIImpactFeedbackGenerator(style: .light).impactOccurred()
                            onConfirm(centerCoordinate)
                        }
                        .font(.makanBody(15))
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 14)
                        .background(Color.sambalRed)
                        .clipShape(Capsule())
                        .padding(.horizontal, 16)
                    }
                    .padding(.bottom, 24)
                }

                VStack {
                    HStack {
                        Spacer()
                        Button {
                            recenterOnMe()
                        } label: {
                            Image(systemName: "location.fill")
                                .font(.system(size: 16, weight: .medium))
                                .foregroundStyle(Color.kicap)
                                .frame(width: 40, height: 40)
                                .background(.white)
                                .clipShape(Circle())
                                .shadow(color: .black.opacity(0.15), radius: 4, y: 2)
                        }
                        .padding(.trailing, 16)
                    }
                    Spacer()
                }
                .padding(.top, 12)
            }
            .navigationTitle(Copy.communityChooseOnMap)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
            }
        }
    }

    private func recenterOnMe() {
        guard case .authorized(let coordinate) = locationService.state else {
            locationService.requestLocation()
            return
        }
        withAnimation {
            cameraPosition = .region(MKCoordinateRegion(center: coordinate, span: MKCoordinateSpan(latitudeDelta: 0.01, longitudeDelta: 0.01)))
        }
    }

    @MainActor
    private func reverseGeocode(_ coordinate: CLLocationCoordinate2D) async {
        let geocoder = CLGeocoder()
        let location = CLLocation(latitude: coordinate.latitude, longitude: coordinate.longitude)
        guard let placemark = try? await geocoder.reverseGeocodeLocation(location).first else {
            addressPreview = nil
            return
        }
        addressPreview = [placemark.thoroughfare, placemark.locality].compactMap { $0 }.joined(separator: ", ")
    }
}
