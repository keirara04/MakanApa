import SwiftUI

/// Admin queue for "my university/area isn't listed" requests submitted from
/// `CommunityAssignmentSheet`. Resolving a request creates the real University/Area row (so it
/// immediately appears in every user's picker) then marks the request resolved in one action —
/// dismissing just closes it out with no side effect, for junk/duplicate requests.
struct CommunityRequestsView: View {
    @State private var status = "pending"
    @State private var requests: [AdminCommunityRequest] = []
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var addContext: AddContext?

    private let statuses = ["pending", "resolved", "dismissed"]

    private struct AddContext: Identifiable {
        let request: AdminCommunityRequest
        var id: Int { request.id }
    }

    var body: some View {
        List {
            Picker("Status", selection: $status) {
                ForEach(statuses, id: \.self) { s in
                    Text(s.capitalized).tag(s)
                }
            }
            .pickerStyle(.segmented)
            .listRowSeparator(.hidden)

            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }

            ForEach(requests) { request in
                row(for: request)
            }
        }
        .navigationTitle("Community Requests")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .onChange(of: status) { _, _ in Task { await load() } }
        .refreshable { await load() }
        .sheet(item: $addContext) { context in
            AddCommunitySheet(request: context.request) { await load() }
        }
    }

    private func row(for request: AdminCommunityRequest) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack {
                Text(request.type == "university" ? "University" : "Area")
                    .capsuleTagStyle()
                Text(request.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
            }
            if let email = request.requesterEmail {
                Text(email).font(.makanBody(12)).foregroundStyle(.secondary)
            }
            if request.status == "pending" {
                HStack(spacing: 12) {
                    Button("Add \(request.type == "university" ? "University" : "Area")") {
                        addContext = AddContext(request: request)
                    }
                    .font(.makanBody(13))
                    Button("Dismiss", role: .destructive) { Task { await dismiss(request) } }
                        .font(.makanBody(13))
                }
                .padding(.top, 2)
            }
        }
        .padding(.vertical, 2)
    }

    @MainActor
    private func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await APIClient.adminListCommunityRequests(status: status)
            requests = response.requests
        } catch {
            errorMessage = "Couldn't load requests."
        }
    }

    @MainActor
    private func dismiss(_ request: AdminCommunityRequest) async {
        do {
            _ = try await APIClient.adminDismissCommunityRequest(id: request.id)
            await load()
        } catch {
            errorMessage = "Couldn't dismiss. Try again."
        }
    }
}

/// Prefilled with the requester's typed name; short name is editable since a free-text request
/// ("Universiti Malaya") isn't necessarily a good short code ("UM") on its own.
private struct AddCommunitySheet: View {
    let request: AdminCommunityRequest
    let onHandled: () async -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var name: String
    @State private var shortName = ""
    @State private var isSaving = false
    @State private var errorMessage: String?

    init(request: AdminCommunityRequest, onHandled: @escaping () async -> Void) {
        self.request = request
        self.onHandled = onHandled
        _name = State(initialValue: request.name)
    }

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("Name", text: $name)
                    TextField("Short name", text: $shortName)
                        .textInputAutocapitalization(.characters)
                }
                if let errorMessage {
                    Text(errorMessage).foregroundStyle(Color.sambalRed)
                }
            }
            .navigationTitle(request.type == "university" ? "Add University" : "Add Area")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    if isSaving {
                        ProgressView()
                    } else {
                        Button("Add") { Task { await save() } }
                            .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty || shortName.trimmingCharacters(in: .whitespaces).isEmpty)
                    }
                }
            }
        }
    }

    @MainActor
    private func save() async {
        isSaving = true
        defer { isSaving = false }
        let trimmedName = name.trimmingCharacters(in: .whitespaces)
        let trimmedShortName = shortName.trimmingCharacters(in: .whitespaces)
        do {
            if request.type == "university" {
                _ = try await APIClient.adminCreateUniversity(name: trimmedName, shortName: trimmedShortName)
            } else {
                _ = try await APIClient.adminCreateArea(name: trimmedName, shortName: trimmedShortName)
            }
            _ = try await APIClient.adminResolveCommunityRequest(id: request.id)
            await onHandled()
            dismiss()
        } catch {
            errorMessage = "Couldn't add. Check the short name isn't already taken."
        }
    }
}
