import Foundation

struct Preference {
    var moodTags: [String] = []
    var cuisines: [String] = []
    var budgetMax: Int
    var maxDistanceKm: Double
    var latitude: Double
    var longitude: Double

    var isAnything: Bool {
        moodTags.isEmpty && cuisines.isEmpty
    }
}
