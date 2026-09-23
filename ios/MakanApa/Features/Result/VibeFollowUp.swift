import Foundation
import Observation
import SwiftUI
import UIKit

/// One accepted decision still waiting for its "what vibe was it?" answer.
struct PendingVibePrompt: Codable, Equatable, Identifiable {
    var id: Int { decisionId }
    let decisionId: Int
    let clientToken: String
    let restaurantName: String
    let acceptedAt: Date
}

/// "What vibe was it?" is a question about the meal, so it's asked after the meal — the next time
/// the user is on Home at least an hour after tapping JOM MAKAN — not the moment they accept,
/// before they've even left. Still roughly every other accept, same cadence as before.
@MainActor
@Observable
final class PendingVibePromptStore {
    static let shared = PendingVibePromptStore()

    private static let pendingKey = "PendingVibePromptStore.pending"
    private static let acceptCountKey = "ResultView.acceptCount"
    private static let minimumDelay: TimeInterval = 60 * 60
    private static let expiry: TimeInterval = 24 * 60 * 60

    private(set) var pending: PendingVibePrompt?
    /// Accept can fire twice for one decision (👍 then "Directions") — count it once.
    private var lastRecordedDecisionId: Int?

    private init() {
        if let data = UserDefaults.standard.data(forKey: Self.pendingKey) {
            pending = try? JSONDecoder().decode(PendingVibePrompt.self, from: data)
        }
    }

    func recordAccept(decisionId: Int, clientToken: String, restaurantName: String) {
        guard lastRecordedDecisionId != decisionId else { return }
        lastRecordedDecisionId = decisionId

        let count = UserDefaults.standard.integer(forKey: Self.acceptCountKey) + 1
        UserDefaults.standard.set(count, forKey: Self.acceptCountKey)
        guard count % 2 == 0 else { return }

        store(PendingVibePrompt(decisionId: decisionId, clientToken: clientToken, restaurantName: restaurantName, acceptedAt: Date()))
    }

    /// The pending prompt once it's old enough to ask about, else nil. Drops it after a day —
    /// nobody remembers yesterday's lunch vibe well enough for the answer to mean much.
    func due(now: Date = Date()) -> PendingVibePrompt? {
        guard let pending else { return nil }
        let age = now.timeIntervalSince(pending.acceptedAt)
        if age >= Self.expiry {
            store(nil)
            return nil
        }
        return age >= Self.minimumDelay ? pending : nil
    }

    func answer(_ prompt: PendingVibePrompt, with tag: CommunityTag) {
        store(nil)
        Task {
            _ = try? await APIClient.submitVibeTag(decisionId: prompt.decisionId, clientToken: prompt.clientToken, vibe: tag)
        }
    }

    func dismiss() {
        store(nil)
    }

    private func store(_ prompt: PendingVibePrompt?) {
        pending = prompt
        if let prompt, let data = try? JSONEncoder().encode(prompt) {
            UserDefaults.standard.set(data, forKey: Self.pendingKey)
        } else {
            UserDefaults.standard.removeObject(forKey: Self.pendingKey)
        }
    }
}

/// The one-tap vibe question, now asked about a specific past meal.
struct VibeFollowUpSheet: View {
    let prompt: PendingVibePrompt
    let onAnswer: (CommunityTag) -> Void
    let onSkip: () -> Void

    var body: some View {
        VStack(spacing: 16) {
            VStack(spacing: 4) {
                Text("How was \(prompt.restaurantName)?")
                    .font(.makanDisplay(18))
                    .foregroundStyle(Color.kicap)
                    .multilineTextAlignment(.center)
                Text("Pick the vibe — helps the next person decide.")
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }

            LazyVGrid(columns: [GridItem(.adaptive(minimum: 90))], spacing: 10) {
                tagButton(.chill, label: "☕ Chill")
                tagButton(.study, label: "📚 Study")
                tagButton(.studentBudget, label: "💸 Student")
                tagButton(.lateNight, label: "🌙 Late night")
                tagButton(.hiddenGem, label: "✨ Hidden gem")
            }

            Button("Skip", action: onSkip)
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .frame(minHeight: 44)
        }
        .padding(20)
    }

    private func tagButton(_ tag: CommunityTag, label: String) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            onAnswer(tag)
        } label: {
            Text(label)
                .font(.makanBody(13))
                .foregroundStyle(Color.kicap)
                .padding(.horizontal, 12)
                .padding(.vertical, 8)
                .frame(maxWidth: .infinity)
                .background(Color.kicap.opacity(0.06))
                .clipShape(Capsule())
        }
        .buttonStyle(PressCompressStyle())
    }
}
