import SwiftUI
import MapKit
import UIKit

/// Everything the user tapped ♡ on in Nearby — the only place `PlacePreferencesStore.savedPlaces`
/// surfaces, since Save is otherwise a silent per-place toggle with no list of its own.
struct FavoritesView: View {
    private var preferences = PlacePreferencesStore.shared
    private var upgradeNudge = GuestUpgradeNudge.shared

    var body: some View {
        Group {
            if preferences.savedPlaces.isEmpty {
                emptyState
            } else {
                List {
                    if upgradeNudge.isEligible(.saves) {
                        GuestUpgradeCard(reason: .saves)
                            .listRowInsets(EdgeInsets(top: 8, leading: 16, bottom: 8, trailing: 16))
                            .listRowBackground(Color.clear)
                            .listRowSeparator(.hidden)
                    }
                    ForEach(preferences.savedPlaces) { place in
                        row(for: place)
                    }
                    .onDelete(perform: delete)
                }
            }
        }
        .navigationTitle("Saved")
        .navigationBarTitleDisplayMode(.inline)
    }

    private var emptyState: some View {
        VStack(spacing: 10) {
            Image(systemName: "heart")
                .font(.system(size: 36))
                .foregroundStyle(Color.kicap.opacity(0.3))
            Text("No saved places yet")
                .font(.makanBody(15))
                .foregroundStyle(.secondary)
            Text("Tap ♡ on a place in Nearby to save it here.")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding()
    }

    private func row(for place: SavedPlace) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            openDirections(to: place)
        } label: {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(place.name)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)

                    HStack(spacing: 6) {
                        if let rating = place.rating {
                            Label(String(format: "%.1f", rating), systemImage: "star.fill")
                                .foregroundStyle(Color.kunyit)
                        }
                        if let spend = PricePresentation.approximateSpendLabel(for: place.priceLevel) {
                            Text("· \(spend)")
                        }
                    }
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
                }

                Spacer()

                Image(systemName: "arrow.up.right")
                    .foregroundStyle(.secondary)
                    .font(.system(size: 13, weight: .semibold))
            }
        }
    }

    private func delete(at offsets: IndexSet) {
        for index in offsets {
            preferences.removeSaved(preferences.savedPlaces[index].id)
        }
    }

    private func openDirections(to place: SavedPlace) {
        let coordinate = CLLocationCoordinate2D(latitude: place.latitude, longitude: place.longitude)
        let destination = MapDestination(coordinate: coordinate, name: place.name, googleMapsURL: nil)
        PreferredMapsLauncher.open(destination: destination, provider: MapProviderPreference.current)
    }
}
