import SwiftUI
import CoreLocation
import MapKit
import UIKit

struct NearbyView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var soloViewModel
    @Environment(LocationService.self) private var locationService
    @State private var viewModel = NearbyViewModel()
    @State private var currentViewport: MapViewport?
    @State private var currentZoom: Float = 15

    var body: some View {
        ZStack(alignment: .top) {
            mapLayer
                .ignoresSafeArea(edges: .bottom)

            VStack(spacing: 10) {
                filterBar
                if viewModel.isZoomedTooFarOut {
                    zoomPrompt
                } else if viewModel.showSearchThisArea {
                    searchThisAreaPill
                }
            }
            .padding(.horizontal, 12)
            .padding(.top, 8)

            VStack {
                Spacer()
                pickOneLahButton
                    .padding(.bottom, 8)
            }
        }
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
            initialCameraTarget: userCoordinate,
            onCameraIdle: { viewport, zoom in
                currentViewport = viewport
                currentZoom = zoom
                Task { await viewModel.viewportSettled(viewport, zoom: zoom) }
            },
            onMarkerTapped: { place in
                viewModel.placeDetails = nil
                viewModel.selectedPlace = place
                Task { await viewModel.loadDetails(for: place) }
            }
        )
    }

    private var userCoordinate: CLLocationCoordinate2D? {
        if case let .authorized(coordinate) = locationService.state {
            return coordinate
        }
        return nil
    }

    // MARK: - Filter bar

    private var filterBar: some View {
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
            Spacer()
        }
    }

    private func filterChip(label: String, isOn: Bool, action: @escaping () -> Void) -> some View {
        Button(action: {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            action()
        }) {
            Text(label)
                .font(.makanBody(13))
                .foregroundStyle(isOn ? .white : Color.kicap)
                .padding(.horizontal, 14)
                .padding(.vertical, 8)
                .background(isOn ? Color.sambalRed : Color.white)
                .clipShape(Capsule())
                .shadow(color: .black.opacity(0.08), radius: 4, y: 2)
        }
    }

    private func rerunSearch() {
        guard let currentViewport else { return }
        Task { await viewModel.searchThisAreaTapped(currentViewport) }
    }

    // MARK: - Zoom / search-this-area prompts

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

    // MARK: - Pick one lah

    private var pickOneLahButton: some View {
        Button {
            UIImpactFeedbackGenerator(style: .medium).impactOccurred()
            Task { await pickOneLah() }
        } label: {
            Text(viewModel.isPicking ? "Nasi tengah fikir..." : "🍚 Pick one lah")
                .font(.makanDisplay(17))
                .foregroundStyle(.white)
                .padding(.horizontal, 24)
                .padding(.vertical, 16)
                .background(Color.sambalRed)
                .clipShape(Capsule())
                .shadow(color: .black.opacity(0.2), radius: 8, y: 4)
        }
        .disabled(viewModel.isPicking || viewModel.places.isEmpty)
        .opacity(viewModel.places.isEmpty ? 0.5 : 1)
    }

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

    // MARK: - Marker tap sheet

    @ViewBuilder
    private func placeSheet(for place: NearbyPlace) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                placePhoto(for: place)

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
                } else if let details = viewModel.placeDetails, !details.reviews.isEmpty {
                    reviewsSection(for: details)
                }
            }
            .padding(20)
        }
    }

    @ViewBuilder
    private func placePhoto(for place: NearbyPlace) -> some View {
        let photoURL = viewModel.placeDetails?.photos.first.flatMap { URL(string: $0.url) }

        ZStack {
            if let photoURL {
                AsyncImage(url: photoURL) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        placePhotoPlaceholder
                    }
                }
            } else {
                placePhotoPlaceholder
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
