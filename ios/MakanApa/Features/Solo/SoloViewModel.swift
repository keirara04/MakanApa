import Foundation
import Observation
import CoreLocation

@Observable
final class SoloViewModel {
    /// Single source of truth for the craving step — one active choice at a time, never a
    /// parallel tag/text pair that can drift out of sync. `MoodOption`/`moodOptions` below keep
    /// their existing name (avoiding view-hierarchy rename churn) even though the vocabulary is
    /// no longer abstract moods but concrete cravings.
    enum CravingSelection: Equatable {
        case tag(String)
        case custom(String)
        case anything
    }

    var cravingSelection: CravingSelection?
    var budgetMax: Int? = 2
    var maxDistanceKm: Double = 2.0

    /// Derives the outgoing request fields from `cravingSelection` — the only place this
    /// mapping happens, so iOS and the wire format can't fall out of sync.
    var outgoingMoods: [String] {
        if case .tag(let tag) = cravingSelection { return [tag] }
        return []
    }

    var outgoingCraving: String? {
        guard case .custom(let text) = cravingSelection else { return nil }
        let normalized = text
            .components(separatedBy: .whitespacesAndNewlines)
            .filter { !$0.isEmpty }
            .joined(separator: " ")
        return normalized.isEmpty ? nil : normalized
    }

    private(set) var decisionId: Int?
    private var clientToken: String?
    private(set) var currentPick: RecommendationResponse.Recommendation?
    private(set) var apiError: APIError?
    private(set) var isEmptyResult = false

    private var lastCoordinate: CLLocationCoordinate2D?
    private var pickSource = "solo"

    struct MoodOption {
        let tag: String
        let illustration: String
        let label: String
        let subtext: String
    }

    struct BudgetOption {
        /// nil = "Anything lah" — no price filter, not the top tier.
        let tier: Int?
        let illustration: String
        let amount: String
        let label: String
    }

    struct DistanceOption {
        let km: Double
        let illustration: String
        let label: String
        let subtext: String
    }

    static let moodOptions: [MoodOption] = [
        MoodOption(tag: "nasi_kandar", illustration: "MoodNasiKandar", label: "Nasi Kandar", subtext: Copy.moodNasiKandarSubtext),
        MoodOption(tag: "ayam_gepuk", illustration: "MoodAyamGepuk", label: "Ayam Gepuk", subtext: Copy.moodAyamGepukSubtext),
        MoodOption(tag: "nasi_padang", illustration: "MoodNasiPadang", label: "Nasi Padang", subtext: Copy.moodNasiPadangSubtext),
        MoodOption(tag: "mee_goreng", illustration: "MoodMeeGoreng", label: "Mee Goreng", subtext: Copy.moodMeeGorengSubtext),
        MoodOption(tag: "nasi_lemak", illustration: "MoodNasiLemak", label: "Nasi Lemak", subtext: Copy.moodNasiLemakSubtext),
        MoodOption(tag: "char_kuey_teow", illustration: "MoodCharKueyTeow", label: "Char Kuey Teow", subtext: Copy.moodCharKueyTeowSubtext),
        MoodOption(tag: "banana_leaf_rice", illustration: "MoodBananaLeafRice", label: "Banana Leaf Rice", subtext: Copy.moodBananaLeafRiceSubtext),
        MoodOption(tag: "dim_sum", illustration: "MoodDimSum", label: "Dim Sum", subtext: Copy.moodDimSumSubtext),
        MoodOption(tag: "healthy", illustration: "MoodLight", label: "Healthy", subtext: Copy.moodLightSubtext),
        MoodOption(tag: "quick", illustration: "MoodQuick", label: "Quick", subtext: Copy.moodQuickSubtext),
    ]

    static let budgetOptions: [BudgetOption] = [
        BudgetOption(tier: 1, illustration: "BudgetSave", amount: "~RM10", label: "save sikit"),
        BudgetOption(tier: 2, illustration: "BudgetNormal", amount: "~RM20", label: "normal lah"),
        BudgetOption(tier: 3, illustration: "BudgetTreat", amount: "~RM35+", label: "feeling kaya"),
    ]

    static let distanceOptions: [DistanceOption] = [
        DistanceOption(km: 1.0, illustration: "DistanceNear", label: "5 min", subtext: "dekat je"),
        DistanceOption(km: 2.0, illustration: "DistanceWalk", label: "10 min", subtext: "okay lah"),
        DistanceOption(km: 5.0, illustration: "DistanceCar", label: "Don't mind", subtext: "janji sedap"),
    ]

    @MainActor
    func decide(coordinate: CLLocationCoordinate2D) async {
        pickSource = "solo"
        lastCoordinate = coordinate
        apiError = nil
        isEmptyResult = false
        do {
            let response = try await APIClient.recommendSolo(
                latitude: coordinate.latitude,
                longitude: coordinate.longitude,
                budgetMax: budgetMax,
                maxDistanceKm: maxDistanceKm,
                moods: outgoingMoods,
                craving: outgoingCraving
            )
            decisionId = response.decisionId
            clientToken = response.clientToken
            currentPick = response.recommendation
            isEmptyResult = response.recommendation == nil
        } catch let error as APIError {
            apiError = error
        } catch {
            apiError = .transport(error)
        }
    }

    @MainActor
    func retry() async {
        guard let lastCoordinate else { return }
        await decide(coordinate: lastCoordinate)
    }

    @MainActor
    func reroll() async {
        guard let decisionId, let clientToken else { return }
        apiError = nil
        do {
            let response = try await APIClient.reroll(decisionId: decisionId, clientToken: clientToken)
            currentPick = response.recommendation
            isEmptyResult = response.recommendation == nil
        } catch let error as APIError {
            // A user-initiated cancel (tapping "Cancel" mid-reroll) surfaces as a transport
            // error wrapping CancellationError — that's not a real failure, so don't flash the
            // error screen over what should just look like returning to the previous pick.
            if case .transport(let underlying) = error, underlying is CancellationError { return }
            apiError = error
        } catch {
            apiError = .transport(error)
        }
    }

    @MainActor
    func acceptCurrentPick() async {
        guard let decisionId, let clientToken else { return }
        _ = try? await APIClient.accept(decisionId: decisionId, clientToken: clientToken)

        if let pick = currentPick {
            RecentDecisionStore.shared.record(RecentDecision(
                id: pick.id, name: pick.name, latitude: pick.latitude, longitude: pick.longitude,
                foodCategory: pick.foodCategory, priceLevel: pick.priceLevel, rating: pick.rating,
                timestamp: Date(), source: pickSource
            ))
        }
    }

    /// Nearby's "Pick one lah" ends a decision exactly like Decide does, so it hands its result
    /// off here rather than ResultView (and its reroll/accept flow) growing a second code path.
    /// Clears mood/budget since Nearby doesn't set them — ResultView's reason chips degrade
    /// gracefully to just a distance-if-any chip, never a stale Decide preference.
    @MainActor
    func adoptExternalPick(
        decisionId: Int?, clientToken: String?,
        recommendation: RecommendationResponse.Recommendation?, error: APIError?
    ) {
        pickSource = "nearby"
        cravingSelection = nil
        budgetMax = nil
        self.decisionId = decisionId
        self.clientToken = clientToken
        currentPick = recommendation
        apiError = error
        isEmptyResult = recommendation == nil && error == nil
    }
}
