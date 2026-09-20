import SwiftUI

struct CommunityPlacesView: View {
    @State private var status = "pending"
    @State private var submissions: [AdminSubmission] = []
    @State private var isLoading = false
    @State private var errorMessage: String?

    private let statuses = ["pending", "approved", "changes_requested", "rejected", "cancelled"]

    var body: some View {
        List {
            Picker("Status", selection: $status) {
                ForEach(statuses, id: \.self) { s in
                    Text(s.replacingOccurrences(of: "_", with: " ").capitalized).tag(s)
                }
            }
            .pickerStyle(.segmented)
            .listRowSeparator(.hidden)

            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }

            ForEach(submissions) { submission in
                NavigationLink {
                    SubmissionReviewView(submission: submission, onHandled: { load() })
                } label: {
                    row(for: submission)
                }
            }
        }
        .navigationTitle("Community Places")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .onChange(of: status) { _, _ in Task { await load() } }
        .refreshable { await load() }
    }

    private func row(for submission: AdminSubmission) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(submission.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                Spacer()
                CommunityBadge(affiliationType: submission.submitter.affiliationType, university: submission.submitter.university)
            }
            Text(submission.sourceType == .google ? "Google" : "Manual")
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
            if let duplicate = submission.possibleDuplicate {
                Text("⚠️ Possible duplicate: \(duplicate.name) (\(duplicate.distanceMeters)m away)")
                    .font(.makanBody(11))
                    .foregroundStyle(Color.kunyit)
            }
        }
        .padding(.vertical, 2)
    }

    @MainActor
    private func load() {
        Task {
            isLoading = true
            defer { isLoading = false }
            do {
                let response = try await APIClient.adminListSubmissions(status: status)
                submissions = response.submissions
            } catch {
                errorMessage = "Couldn't load submissions."
            }
        }
    }
}

private struct SubmissionReviewView: View {
    let submission: AdminSubmission
    let onHandled: () -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var isProcessing = false
    @State private var showingRejectSheet = false
    @State private var showingChangesSheet = false
    @State private var reviewNote = ""
    @State private var errorMessage: String?

    var body: some View {
        List {
            Section {
                HStack { Text("Name"); Spacer(); Text(submission.name).foregroundStyle(.secondary) }
                HStack { Text("Category"); Spacer(); Text(submission.foodCategory ?? "—").foregroundStyle(.secondary) }
                HStack { Text("Price"); Spacer(); Text(PricePresentation.approximateSpendLabel(for: submission.priceLevel) ?? "—").foregroundStyle(.secondary) }
                if let address = submission.address {
                    HStack { Text("Address"); Spacer(); Text(address).foregroundStyle(.secondary) }
                }
                HStack { Text("Source"); Spacer(); Text(submission.sourceType == .google ? "Google" : "Manual").foregroundStyle(.secondary) }
            }

            if let notes = submission.notes {
                Section("Submitter notes") {
                    Text(notes).font(.makanBody(13)).foregroundStyle(.secondary)
                }
            }

            Section("Submitter") {
                HStack {
                    Text(submission.submitter.email ?? "Contributor unavailable")
                    Spacer()
                    CommunityBadge(affiliationType: submission.submitter.affiliationType, university: submission.submitter.university)
                }
            }

            if let duplicate = submission.possibleDuplicate {
                Section("Possible duplicate") {
                    VStack(alignment: .leading, spacing: 8) {
                        HStack {
                            VStack(alignment: .leading) {
                                Text("Submitted").font(.makanBody(11)).foregroundStyle(.secondary)
                                Text(submission.name).font(.makanBody(13))
                            }
                            Spacer()
                            Text("\(duplicate.distanceMeters)m apart").font(.makanBody(11)).foregroundStyle(.secondary)
                            Spacer()
                            VStack(alignment: .trailing) {
                                Text("Existing").font(.makanBody(11)).foregroundStyle(.secondary)
                                Text(duplicate.name).font(.makanBody(13))
                            }
                        }
                        Button("Link to existing") {
                            Task { await link(restaurantId: duplicate.id) }
                        }
                        .font(.makanBody(14))
                    }
                }
            }

            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }

            Section("Decision") {
                Button("Approve") { Task { await approve() } }
                    .disabled(isProcessing)
                Button("Request changes") { showingChangesSheet = true }
                    .disabled(isProcessing)
                Button("Reject", role: .destructive) { showingRejectSheet = true }
                    .disabled(isProcessing)
            }
        }
        .navigationTitle("Review")
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $showingRejectSheet) {
            reviewNoteSheet(title: "Reject", action: reject)
        }
        .sheet(isPresented: $showingChangesSheet) {
            reviewNoteSheet(title: "Request changes", action: requestChanges)
        }
    }

    private func reviewNoteSheet(title: String, action: @escaping () async -> Void) -> some View {
        NavigationStack {
            Form {
                TextField("Reason", text: $reviewNote, axis: .vertical)
            }
            .navigationTitle(title)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { reviewNote = ""; showingRejectSheet = false; showingChangesSheet = false }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Send") { Task { await action() } }
                        .disabled(reviewNote.trimmingCharacters(in: .whitespaces).isEmpty)
                }
            }
        }
    }

    @MainActor
    private func approve() async {
        isProcessing = true
        defer { isProcessing = false }
        do {
            _ = try await APIClient.adminApproveSubmission(id: submission.id)
            onHandled()
            dismiss()
        } catch {
            errorMessage = "Couldn't approve. Try again."
        }
    }

    @MainActor
    private func link(restaurantId: Int) async {
        isProcessing = true
        defer { isProcessing = false }
        do {
            _ = try await APIClient.adminLinkSubmission(id: submission.id, restaurantId: restaurantId)
            onHandled()
            dismiss()
        } catch {
            errorMessage = "Couldn't link. Try again."
        }
    }

    @MainActor
    private func reject() async {
        isProcessing = true
        defer { isProcessing = false }
        do {
            _ = try await APIClient.adminRejectSubmission(id: submission.id, reviewNote: reviewNote)
            showingRejectSheet = false
            onHandled()
            dismiss()
        } catch {
            errorMessage = "Couldn't reject. Try again."
        }
    }

    @MainActor
    private func requestChanges() async {
        isProcessing = true
        defer { isProcessing = false }
        do {
            _ = try await APIClient.adminRequestChanges(id: submission.id, reviewNote: reviewNote)
            showingChangesSheet = false
            onHandled()
            dismiss()
        } catch {
            errorMessage = "Couldn't request changes. Try again."
        }
    }
}
