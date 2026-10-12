import Foundation

/// Approximate per-person spend, formatted from Google's coarse price level.
/// Single source of truth for this specific "≈ RMxx/person" phrasing — not shared with
/// the Solo wizard's shorter tile labels (`SoloViewModel.budgetOptions`), a different display need.
enum PricePresentation {
    /// `region` is where the place is (a KL place viewed from Seoul still says RM); defaults to the user's.
    static func approximateSpendLabel(for priceLevel: Int?, region: Region = .current) -> String? {
        region.approximateSpend(priceLevel)
    }
}
