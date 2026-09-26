import SwiftUI

struct MySubmissionsView: View {
    @State private var submissions: [MySubmission] = []
    @State private var isLoading = false
    @State private var errorMessage: String?

    var body: some View {
        AccountRequired(feature: "see places you've added", requiresTerms: false) { accountContent }
    }

    @ViewBuilder
    private var accountContent: some View {
        List {
            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }
            ForEach(submissions) { submission in
                VStack(alignment: .leading, spacing: 6) {
                    Text(submission.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                    if submission.submissionType == .halalReport {
                        Text(halalSubtitle(for: submission)).font(.makanBody(12)).foregroundStyle(.secondary)
                    } else if submission.submissionType == .ownerClaim {
                        Text("Ownership claim").font(.makanBody(12)).foregroundStyle(.secondary)
                    } else if let category = submission.foodCategory {
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

    private func halalSubtitle(for submission: MySubmission) -> String {
        let claim = submission.halalClaim?.pickerLabel ?? "Halal"
        if let resolved = submission.halalResolvedStatus, resolved != submission.halalClaim {
            return "Halal report: \(claim) · reviewed as \(resolved.pickerLabel)"
        }
        return "Halal report: \(claim)"
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
