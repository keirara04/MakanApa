import Foundation

/// Mirrors the backend's App\Support\DiscoveryMode — must stay in sync with those raw string
/// values, since they're sent as-is in request bodies/query params.
enum DiscoveryMode: String, Codable, CaseIterable {
    case normal
    case popular
    case lowKey = "low_key"
    case cafe
    case cheapEats = "cheap_eats"
    case lateNight = "late_night"
}

/// Mirrors the backend's App\Support\Vibe — search-time filter, composable with DiscoveryMode.
enum Vibe: String, Codable, CaseIterable {
    case chill
    case study
    case dessert
    case coffee
    case brunch
    case lateNight = "late_night"
}

/// Mirrors the backend's App\Support\CommunityTag — post-accept feedback taxonomy, broader
/// than Vibe. Posted via the vibe-tag endpoint, never used as a search filter.
enum CommunityTag: String, Codable, CaseIterable {
    case chill
    case study
    case studentBudget = "student_budget"
    case hiddenGem = "hidden_gem"
    case date
    case lepak
    case family
    case lateNight = "late_night"
}
