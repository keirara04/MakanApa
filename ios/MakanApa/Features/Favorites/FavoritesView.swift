import SwiftUI
import MapKit
import UIKit

/// Everything the user saved — tapped ♡ on in Nearby, or found with this screen's own search. The
/// search checks the saved list first, then places around the user (the same search Nearby uses);
/// a place nobody has listed yet hands off to Add a place with the search already typed.
struct FavoritesView: View {
    private var preferences = PlacePreferencesStore.shared
    private var upgradeNudge = GuestUpgradeNudge.shared
    @Environment(LocationService.self) private var locationService
    @State private var savedPick: SavedPickLaunch?

    @State private var query = ""
    @State private var results: [PlaceSearchResult] = []
    @State private var isSearching = false
    @State private var searchFailed = false
    @State private var retryCount = 0
    /// Google-only rows become real places when saved; this remembers which id each one became.
    @State private var resolvedIds: [String: Int] = [:]
    @State private var savingIds: Set<String> = []
    @State private var saveError: String?
    @State private var showingAddPlace = false

    private static let searchRadiusKm = 25.0

    var body: some View {
        Group {
            if isSearchActive {
                searchList
            } else if preferences.savedPlaces.isEmpty {
                emptyState
            } else {
                savedList
            }
        }
        .searchable(text: $query, placement: .navigationBarDrawer(displayMode: .always), prompt: Copy.savedSearchPrompt)
        .autocorrectionDisabled()
        .task(id: "\(trimmedQuery)#\(retryCount)") { await search() }
        .sensoryFeedback(.impact(weight: .light), trigger: preferences.savedPlaces.count)
        .savedPickCover($savedPick)
        .sheet(isPresented: $showingAddPlace) {
            AddPlaceFlow(prefillQuery: trimmedQuery)
        }
        .navigationTitle("Saved")
        .navigationBarTitleDisplayMode(.inline)
    }

    private var trimmedQuery: String { query.trimmingCharacters(in: .whitespaces) }
    private var isSearchActive: Bool { trimmedQuery.count >= 2 }

    // MARK: - Saved list

    private var savedList: some View {
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

            Section {
                pickOneButton
            } footer: {
                if preferences.savedPlaces.count < 2 {
                    Text(Copy.savedPickNeedMore)
                }
            }
        }
    }

    /// Opens the card shuffle right over this sheet; closing it lands back on this list.
    private var pickOneButton: some View {
        Button {
            savedPick = SavedPickLaunch.make(places: preferences.savedPlaces, location: locationService.state)
        } label: {
            Label(Copy.savedPickButton, systemImage: "rectangle.stack.fill")
                .font(.makanBody(15).weight(.semibold))
                .foregroundStyle(preferences.savedPlaces.count < 2 ? Color.kicapSecondary : Color.sambalRed)
                .frame(maxWidth: .infinity, minHeight: 44)
        }
        .disabled(preferences.savedPlaces.count < 2)
        .accessibilityHint(Copy.savedPickHint)
    }

    private var emptyState: some View {
        VStack(spacing: 10) {
            Image(systemName: "heart")
                .font(.system(size: 36))
                .foregroundStyle(Color.kicap.opacity(0.3))
            Text(Copy.savedEmptyTitle)
                .font(.makanBody(15))
                .foregroundStyle(.secondary)
            Text(Copy.savedEmptyDetail)
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

    // MARK: - Search

    private var searchList: some View {
        let matches = preferences.savedPlaces.filter { $0.name.localizedCaseInsensitiveContains(trimmedQuery) }
        return List {
            if !matches.isEmpty {
                Section(Copy.savedSearchYourPlaces) {
                    ForEach(matches) { row(for: $0) }
                }
            }

            Section {
                if searchFailed {
                    VStack(alignment: .leading, spacing: 8) {
                        Text(Copy.communitySearchFailed)
                            .font(.makanBody(13))
                            .foregroundStyle(.secondary)
                        Button(Copy.tryAgain) { retryCount += 1 }
                            .font(.makanBody(14).weight(.semibold))
                            .foregroundStyle(Color.sambalRed)
                            .frame(minHeight: 44)
                    }
                } else if isSearching && results.isEmpty {
                    HStack {
                        Spacer()
                        ProgressView()
                        Spacer()
                    }
                    .frame(minHeight: 44)
                } else if results.isEmpty && searchOrigin != nil {
                    Text(Copy.savedSearchNoResults(trimmedQuery))
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                }
                ForEach(results) { result in
                    resultRow(result)
                }
            } header: {
                Text(Copy.savedSearchSaveHeader)
            } footer: {
                if let saveError {
                    Text(saveError).foregroundStyle(Color.sambalRed)
                } else if searchOrigin == nil {
                    Text(Copy.savedSearchNeedsLocation)
                }
            }

            Section {
                Button {
                    showingAddPlace = true
                } label: {
                    VStack(alignment: .leading, spacing: 4) {
                        Label(Copy.savedSearchAddNew, systemImage: "plus.circle.fill")
                            .font(.makanBody(15).weight(.semibold))
                            .foregroundStyle(Color.sambalRed)
                        Text(Copy.savedSearchAddNewDetail)
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity, minHeight: 44, alignment: .leading)
                }
            }
        }
    }

    private func resultRow(_ result: PlaceSearchResult) -> some View {
        let restaurantId = result.restaurantId ?? resolvedIds[result.id]
        let saved = restaurantId.map(preferences.isSaved) ?? false
        let busy = savingIds.contains(result.id)

        return Button {
            Task { await toggle(result) }
        } label: {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(result.name)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.leading)
                    if let details = [result.category, distanceText(result.distanceKm)].compactMap({ $0 }).joined(separator: " · ").nilIfBlank {
                        Text(details)
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                            .monospacedDigit()
                    }
                    if let address = result.address {
                        Label(address, systemImage: "mappin")
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                            .lineLimit(1)
                    }
                }
                Spacer(minLength: 8)
                Group {
                    if busy {
                        ProgressView()
                    } else {
                        Image(systemName: saved ? "heart.fill" : "heart")
                            .font(.system(size: 20))
                            .foregroundStyle(Color.sambalRed)
                            .contentTransition(.symbolEffect(.replace))
                    }
                }
                .frame(width: 44, height: 44)
            }
            .contentShape(Rectangle())
        }
        .disabled(busy)
        .accessibilityElement(children: .combine)
        .accessibilityValue(saved ? Copy.savedStateSaved : Copy.savedStateNotSaved)
        .accessibilityHint(Copy.savedToggleHint)
    }

    /// Where to search around: the user, or — without location — the middle of what they've saved.
    private var searchOrigin: CLLocationCoordinate2D? {
        if case let .authorized(coordinate) = locationService.state { return coordinate }
        let places = preferences.savedPlaces
        guard !places.isEmpty else { return nil }
        let count = Double(places.count)
        return CLLocationCoordinate2D(
            latitude: places.map(\.latitude).reduce(0, +) / count,
            longitude: places.map(\.longitude).reduce(0, +) / count
        )
    }

    /// Debounced by `.task(id:)` — each keystroke cancels the last run before it hits the network.
    private func search() async {
        let term = trimmedQuery
        saveError = nil
        guard term.count >= 2 else {
            results = []
            searchFailed = false
            isSearching = false
            return
        }
        try? await Task.sleep(for: .milliseconds(350))
        guard !Task.isCancelled else { return }
        guard let origin = searchOrigin else {
            results = []
            isSearching = false
            return
        }

        isSearching = true
        searchFailed = false
        do {
            let response = try await APIClient.searchPlaces(
                query: term, latitude: origin.latitude, longitude: origin.longitude, radiusKm: Self.searchRadiusKm
            )
            guard !Task.isCancelled else { return }
            results = response.results
        } catch {
            guard !Task.isCancelled else { return }
            results = []
            searchFailed = true
        }
        isSearching = false
    }

    private func toggle(_ result: PlaceSearchResult) async {
        saveError = nil
        if let restaurantId = result.restaurantId ?? resolvedIds[result.id] {
            if preferences.isSaved(restaurantId) {
                preferences.removeSaved(restaurantId)
            } else {
                preferences.save(SavedPlace(
                    id: restaurantId, name: result.name, latitude: result.latitude, longitude: result.longitude,
                    rating: result.rating, priceLevel: result.priceLevel, openStatus: result.openStatus, savedAt: Date()
                ))
            }
            return
        }

        // A Google-only row isn't a MakanApa place yet — resolve it first, the way Nearby does.
        guard let googlePlaceId = result.googlePlaceId else { return }
        savingIds.insert(result.id)
        defer { savingIds.remove(result.id) }
        do {
            let place = try await APIClient.resolvePlace(googlePlaceId: googlePlaceId).restaurant
            resolvedIds[result.id] = place.id
            if !preferences.isSaved(place.id) {
                preferences.toggleSaved(place)
            }
        } catch {
            saveError = Copy.savedSearchSaveFailed
        }
    }

    // MARK: - Actions

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

    private func distanceText(_ km: Double?) -> String? {
        guard let km else { return nil }
        return Measurement(value: km, unit: UnitLength.kilometers)
            .formatted(.measurement(width: .abbreviated, usage: .road, numberFormatStyle: .number.precision(.fractionLength(0...1))))
    }
}

private extension String {
    var nilIfBlank: String? { isEmpty ? nil : self }
}
