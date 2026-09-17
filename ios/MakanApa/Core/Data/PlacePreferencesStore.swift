import Foundation
import Observation

struct SavedPlace: Codable, Identifiable, Equatable {
    let id: Int // provider place id
    let name: String
    let latitude: Double
    let longitude: Double
    let rating: Double?
    let priceLevel: Int?
    let openStatus: String
    let savedAt: Date
}

/// Per-place "Save" / "Don't suggest" state, keyed by the provider's place id (not name — names
/// collide and change). Exclusion is a hard filter callers must apply themselves (Nearby drops
/// excluded places from `places` entirely); saving is a soft signal, never a ranking override.
/// Saved places keep a full snapshot (not just the id) so the Favorites list has something to
/// render without re-fetching from the network.
@MainActor
@Observable
final class PlacePreferencesStore {
    static let shared = PlacePreferencesStore()

    private let defaults: UserDefaults
    private static let savedKey = "PlacePreferencesStore.savedPlaces"
    private static let excludedKey = "PlacePreferencesStore.excludedPlaceIds"

    private(set) var savedPlaces: [SavedPlace]
    private(set) var excludedPlaceIds: Set<Int>

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        if let data = defaults.data(forKey: Self.savedKey),
           let decoded = try? JSONDecoder().decode([SavedPlace].self, from: data) {
            savedPlaces = decoded
        } else {
            savedPlaces = []
        }
        excludedPlaceIds = Set(defaults.array(forKey: Self.excludedKey) as? [Int] ?? [])
    }

    func isSaved(_ placeId: Int) -> Bool { savedPlaces.contains { $0.id == placeId } }
    func isExcluded(_ placeId: Int) -> Bool { excludedPlaceIds.contains(placeId) }

    func toggleSaved(_ place: NearbyPlace) {
        let nowSaved: Bool
        if savedPlaces.contains(where: { $0.id == place.id }) {
            savedPlaces.removeAll { $0.id == place.id }
            nowSaved = false
        } else {
            savedPlaces.insert(SavedPlace(
                id: place.id, name: place.name, latitude: place.latitude, longitude: place.longitude,
                rating: place.rating, priceLevel: place.priceLevel, openStatus: place.openStatus,
                savedAt: Date()
            ), at: 0)
            nowSaved = true
        }
        persistSaved()
        syncSaveState(nowSaved, placeId: place.id)
    }

    /// Favorites' swipe-to-delete — must go through the same server sync as the heart toggle,
    /// otherwise the two paths diverge (server keeps thinking it's saved after this removes it
    /// locally).
    func removeSaved(_ placeId: Int) {
        savedPlaces.removeAll { $0.id == placeId }
        persistSaved()
        syncSaveState(false, placeId: placeId)
    }

    /// Fire-and-forget — the local list is the source of truth for this device's UI; the network
    /// call just also contributes this save/unsave to the shared community signal.
    private func syncSaveState(_ saved: Bool, placeId: Int) {
        Task {
            _ = try? await (saved ? APIClient.saveRestaurant(id: placeId) : APIClient.unsaveRestaurant(id: placeId))
        }
    }

    func exclude(_ placeId: Int) {
        excludedPlaceIds.insert(placeId)
        savedPlaces.removeAll { $0.id == placeId }
        defaults.set(Array(excludedPlaceIds), forKey: Self.excludedKey)
        persistSaved()
    }

    private func persistSaved() {
        if let data = try? JSONEncoder().encode(savedPlaces) {
            defaults.set(data, forKey: Self.savedKey)
        }
    }
}
