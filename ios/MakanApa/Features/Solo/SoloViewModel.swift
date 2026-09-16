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
        let emoji: String
        let label: String
    }

    struct BudgetOption {
        /// nil = "Anything lah" — no price filter, not the top tier.
        let tier: Int?
        let amount: String
        let label: String
    }

    struct DistanceOption {
        let km: Double
        let emoji: String
        let label: String
        let subtext: String
    }

    static let moodOptions: [MoodOption] = [
        MoodOption(tag: "spicy", emoji: "🔥", label: "Spicy"),
        MoodOption(tag: "comfort_food", emoji: "🍜", label: "Comfort"),
        MoodOption(tag: "healthy", emoji: "🥗", label: "Light"),
        MoodOption(tag: "quick", emoji: "⚡", label: "Quick"),
    ]

    static let budgetOptions: [BudgetOption] = [
        BudgetOption(tier: 1, amount: "~RM10", label: "save sikit"),
        BudgetOption(tier: 2, amount: "~RM20", label: "normal lah"),
        BudgetOption(tier: 3, amount: "~RM35+", label: "feeling kaya"),
    ]

    static let distanceOptions: [DistanceOption] = [
        DistanceOption(km: 1.0, emoji: "🚶", label: "5 min", subtext: "dekat je"),
        DistanceOption(km: 2.0, emoji: "🚶‍♂️", label: "10 min", subtext: "okay lah"),
        DistanceOption(km: 5.0, emoji: "🚗", label: "Don't mind", subtext: "janji sedap"),
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
