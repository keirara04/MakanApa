import Foundation
import Observation
import CoreLocation

@Observable
final class SoloViewModel {
    var selectedMoodTags: Set<String> = []
    var budgetMax: Int? = 2
    var maxDistanceKm: Double = 2.0

    private(set) var decisionId: Int?
    private(set) var currentPick: RecommendationResponse.Recommendation?
    private(set) var apiError: APIError?
    private(set) var isEmptyResult = false

    private var lastCoordinate: CLLocationCoordinate2D?

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
        MoodOption(tag: "spicy", illustration: "MoodSpicy", label: "Spicy", subtext: Copy.moodSpicySubtext),
        MoodOption(tag: "comfort_food", illustration: "MoodComfort", label: "Comfort", subtext: Copy.moodComfortSubtext),
        MoodOption(tag: "healthy", illustration: "MoodLight", label: "Light", subtext: Copy.moodLightSubtext),
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
        lastCoordinate = coordinate
        apiError = nil
        isEmptyResult = false
        do {
            let response = try await APIClient.recommendSolo(
                latitude: coordinate.latitude,
                longitude: coordinate.longitude,
                budgetMax: budgetMax,
                maxDistanceKm: maxDistanceKm,
                moods: Array(selectedMoodTags)
            )
            decisionId = response.decisionId
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
        guard let decisionId else { return }
        apiError = nil
        do {
            let response = try await APIClient.reroll(decisionId: decisionId)
            currentPick = response.recommendation
            isEmptyResult = response.recommendation == nil
        } catch let error as APIError {
            apiError = error
        } catch {
            apiError = .transport(error)
        }
    }

    @MainActor
    func acceptCurrentPick() async {
        guard let decisionId else { return }
        _ = try? await APIClient.accept(decisionId: decisionId)
    }
}
