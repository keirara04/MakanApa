import Foundation

/// Approximate per-person spend, formatted from Google's coarse price level.
/// Single source of truth for this specific "≈ RMxx/person" phrasing — not shared with
/// the Solo wizard's shorter tile labels (`SoloViewModel.budgetOptions`), a different display need.
enum PricePresentation {
    static func approximateSpendLabel(for priceLevel: Int?) -> String? {
        switch priceLevel {
        case 1: return "≈ RM10/person"
        case 2: return "≈ RM20/person"
        case 3: return "≈ RM35+/person"
        default: return nil
        }
    }
}
