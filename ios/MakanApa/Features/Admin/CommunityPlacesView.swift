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

            if isLoading && submissions.isEmpty {
                ProgressView().frame(maxWidth: .infinity)
            } else if submissions.isEmpty && errorMessage == nil {
                Text("Nothing here.").font(.makanBody(13)).foregroundStyle(.secondary)
            }

            ForEach(submissions) { submission in
                NavigationLink {
                    SubmissionReviewView(submission: submission, onHandled: { Task { await load() } })
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
            Text("\(Self.typeLabel(submission.submissionType)) · \(submission.sourceType == .google ? "Google" : "Manual")")
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
            if let duplicate = submission.possibleDuplicate {
                Label("Possible duplicate: \(duplicate.name) (\(duplicate.distanceMeters)m away)", systemImage: "exclamationmark.triangle.fill")
                    .font(.makanBody(11))
                    .foregroundStyle(Color.kunyit)
            }
        }
        .padding(.vertical, 2)
    }

    /// Awaited, so pull-to-refresh waits for it; a reply for a tab the admin already left is dropped.
    @MainActor
    private func load() async {
        let requested = status
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await APIClient.adminListSubmissions(status: requested)
            guard requested == status else { return }
            // Halal reports have their own queue, which asks for the status to record.
            submissions = response.submissions.filter { $0.submissionType != .halalReport }
            errorMessage = nil
        } catch {
            guard requested == status else { return }
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't load submissions."
        }
    }

    static func typeLabel(_ type: SubmissionType) -> String {
        switch type {
        case .newPlace: "New place"
        case .editPlace: "Edit"
        case .closure: "Closed down"
        case .reopen: "Reopened"
        case .halalReport: "Halal report"
        case .ownerClaim: "Owner claim"
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
    @State private var showingRemoveConfirm = false
    @State private var showingApproveConfirm = false
    @State private var linkTarget: PossibleDuplicate?
    @State private var reviewNote = ""
    @State private var errorMessage: String?
    @State private var pendingPhotos: [AdminSubmissionPhoto] = []

    var body: some View {
        List {
            Section {
                HStack { Text("Type"); Spacer(); Text(CommunityPlacesView.typeLabel(submission.submissionType)).foregroundStyle(.secondary) }
                HStack { Text("Name"); Spacer(); Text(submission.name).foregroundStyle(.secondary) }
                HStack { Text("Source"); Spacer(); Text(submission.sourceType == .google ? "Google" : "Manual").foregroundStyle(.secondary) }
                if let contactPhone = submission.contactPhone {
                    HStack { Text("Contact"); Spacer(); Text(contactPhone).foregroundStyle(.secondary).textSelection(.enabled) }
                }
            }

            // Only fields the submitter actually flagged as changed — the full snapshot is
            // moderation context, not what gets applied on approve, so the diff shown here only
            // covers what materializeFields()/approveEdit() will actually touch.
            if submission.submissionType == .editPlace && !submission.changedFields.isEmpty {
                Section("Proposed changes") {
                    ForEach(submission.changedFields, id: \.self) { field in
                        HStack {
                            Text(fieldLabel(field))
                            Spacer()
                            Text(fieldValue(field)).foregroundStyle(.secondary)
                        }
                    }
                }
            } else if submission.submissionType == .newPlace {
                Section("Details") {
                    HStack { Text("Category"); Spacer(); Text(submission.foodCategory ?? "None").foregroundStyle(.secondary) }
                    HStack { Text("Price"); Spacer(); Text(PricePresentation.approximateSpendLabel(for: submission.priceLevel, region: region) ?? "None").foregroundStyle(.secondary) }
                    if let address = submission.address {
                        HStack { Text("Address"); Spacer(); Text(address).foregroundStyle(.secondary) }
                    }
                }
            }

            if !pendingPhotos.isEmpty {
                Section("Photos") {
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 10) {
                            ForEach(pendingPhotos) { photo in
                                AsyncImage(url: URL(string: photo.url)) { phase in
                                    switch phase {
                                    case .success(let image): image.resizable().scaledToFill()
                                    default: Color.kicap.opacity(0.06)
                                    }
                                }
                                .frame(width: 100, height: 100)
                                .clipShape(RoundedRectangle(cornerRadius: 12))
                            }
                        }
                    }
                }
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
                        if submission.status == "pending" {
                            Button("Link to existing") { linkTarget = duplicate }
                                .font(.makanBody(14))
                        }
                    }
                }
            }

            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }

            // Removing deactivates the place itself, so only for a community-added place.
            if submission.status == "approved", submission.submissionType == .newPlace, let restaurantId = submission.restaurantId {
                Section("Decision") {
                    Button("Remove from community", role: .destructive) { showingRemoveConfirm = true }
                        .disabled(isProcessing)
                }
                .confirmationDialog(
                    "Remove \(submission.name) from Community?",
                    isPresented: $showingRemoveConfirm,
                    titleVisibility: .visible
                ) {
                    Button("Remove", role: .destructive) { Task { await remove(restaurantId: restaurantId) } }
                    Button("Cancel", role: .cancel) {}
                } message: {
                    Text("It stops showing up in Nearby and Community. This doesn't delete its history, and it can come back through the normal reopen flow.")
                }
            } else if submission.status == "pending" {
                Section("Decision") {
                    Button("Approve") { showingApproveConfirm = true }
                        .disabled(isProcessing)
                    Button("Request changes") { showingChangesSheet = true }
                        .disabled(isProcessing)
                    Button("Reject", role: .destructive) { showingRejectSheet = true }
                        .disabled(isProcessing)
                }
            }
        }
        .navigationTitle("Review")
        .navigationBarTitleDisplayMode(.inline)
        .confirmationDialog("Approve \(CommunityPlacesView.typeLabel(submission.submissionType).lowercased())?", isPresented: $showingApproveConfirm, titleVisibility: .visible) {
            Button("Approve") { Task { await approve() } }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text(approveMessage)
        }
        .confirmationDialog(
            "Link to \(linkTarget?.name ?? "")?",
            isPresented: Binding(get: { linkTarget != nil }, set: { if !$0 { linkTarget = nil } }),
            titleVisibility: .visible
        ) {
            Button("Link") { if let target = linkTarget { Task { await link(restaurantId: target.id) } } }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("This submission is marked as the same place. Nothing new is added, and the submitter is told it was approved.")
        }
        .sheet(isPresented: $showingRejectSheet) {
            reviewNoteSheet(title: "Reject", action: reject)
        }
        .sheet(isPresented: $showingChangesSheet) {
            reviewNoteSheet(title: "Request changes", action: requestChanges)
        }
        .task { await loadPhotos() }
    }

    /// Where the place is, so a Seoul submission reads in won even when the admin is in KL.
    private var region: Region { .of(latitude: submission.latitude, longitude: submission.longitude) }

    private var approveMessage: String {
        switch submission.submissionType {
        case .newPlace: "It goes live in Nearby and Community."
        case .editPlace: "The proposed changes are applied to the place."
        case .closure: "The place is marked closed and stops showing up."
        case .reopen: "The place shows up again."
        case .ownerClaim: "The submitter is recorded as the owner of this place."
        case .halalReport: "Use the Halal review queue for halal reports."
        }
    }

    private func fieldLabel(_ field: String) -> String {
        switch field {
        case "food_category": return "Category"
        case "price_level": return "Price"
        case "instagram_handle": return "Instagram"
        case "tiktok_handle": return "TikTok"
        case "website_url": return "Website"
        case "menu_items": return "Menu"
        default: return field.replacingOccurrences(of: "_", with: " ").capitalized
        }
    }

    private func fieldValue(_ field: String) -> String {
        switch field {
        case "name": return submission.name
        case "address": return submission.address ?? "None"
        case "food_category": return submission.foodCategory ?? "None"
        case "price_level": return PricePresentation.approximateSpendLabel(for: submission.priceLevel, region: region) ?? "None"
        case "phone": return submission.phone ?? "None"
        case "instagram_handle": return submission.instagramHandle ?? "None"
        case "tiktok_handle": return submission.tiktokHandle ?? "None"
        case "website_url": return submission.websiteUrl ?? "None"
        case "menu_items": return "\(submission.menuItems?.count ?? 0) item(s)"
        default: return "None"
        }
    }

    @MainActor
    private func loadPhotos() async {
        pendingPhotos = (try? await APIClient.adminSubmissionPhotos(id: submission.id).photos) ?? []
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
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't approve. Try again."
        }
    }

    @MainActor
    private func remove(restaurantId: Int) async {
        isProcessing = true
        defer { isProcessing = false }
        do {
            _ = try await APIClient.adminRemoveRestaurant(restaurantId: restaurantId)
            onHandled()
            dismiss()
        } catch {
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't remove. Try again."
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
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't link. Try again."
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
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't reject. Try again."
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
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't request changes. Try again."
        }
    }
}
