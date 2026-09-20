import SwiftUI

struct MySubmissionsView: View {
    @State private var submissions: [MySubmission] = []
    @State private var isLoading = false
    @State private var errorMessage: String?

    var body: some View {
        List {
            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }
            ForEach(submissions) { submission in
                VStack(alignment: .leading, spacing: 6) {
                    Text(submission.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                    if let category = submission.foodCategory {
                        Text(category).font(.makanBody(12)).foregroundStyle(.secondary)
                    }
                    statusLabel(for: submission)
                }
                .padding(.vertical, 4)
            }
        }
        .navigationTitle(Copy.communityMyPlacesTitle)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
    }

    @ViewBuilder
    private func statusLabel(for submission: MySubmission) -> some View {
        HStack(spacing: 4) {
            Image(systemName: icon(for: submission.status))
            Text(text(for: submission))
        }
        .font(.makanBody(12))
        .foregroundStyle(color(for: submission.status))
    }

    private func icon(for status: String) -> String {
        switch status {
        case "pending": return "clock"
        case "approved": return "checkmark.circle"
        case "changes_requested": return "exclamationmark.circle"
        case "rejected": return "xmark.circle"
        default: return "circle"
        }
    }

    private func text(for submission: MySubmission) -> String {
        switch submission.status {
        case "pending": return Copy.communityStatusPending
        case "approved": return Copy.communityStatusApproved
        case "changes_requested": return submission.reviewNote.map { "\(Copy.communityStatusChangesRequested): \($0)" } ?? Copy.communityStatusChangesRequested
        case "rejected": return submission.reviewNote.map { "\(Copy.communityStatusRejected): \($0)" } ?? Copy.communityStatusRejected
        case "cancelled": return Copy.communityStatusCancelled
        default: return submission.status.capitalized
        }
    }

    private func color(for status: String) -> Color {
        switch status {
        case "approved": return .secondary
        case "rejected": return Color.sambalRed
        case "changes_requested": return Color.kunyit
        default: return .secondary
        }
    }

    @MainActor
    private func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await APIClient.mySubmissions()
            submissions = response.submissions
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't load your submissions."
        }
    }
}
