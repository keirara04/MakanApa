import SwiftUI
import UIKit

struct AdminUsersView: View {
    @State private var users: [AdminUser] = []
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var searchText = ""
    @State private var showCreateSheet = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private var filteredUsers: [AdminUser] {
        guard !searchText.isEmpty else { return users }
        return users.filter { $0.email.localizedCaseInsensitiveContains(searchText) }
    }

    private var activeCount: Int {
        users.filter { $0.status == "active" }.count
    }

    var body: some View {
        List {
            if let errorMessage {
                Text(errorMessage)
                    .font(.makanBody(13))
                    .foregroundStyle(Color.sambalRed)
            }

            ForEach(filteredUsers) { user in
                NavigationLink {
                    AdminUserDetailView(user: user, onRevoked: { revoked in
                        handleRevoked(revoked)
                    })
                } label: {
                    HStack {
                        VStack(alignment: .leading, spacing: 2) {
                            Text(user.email)
                                .foregroundStyle(Color.kicap)
                            HStack(spacing: 6) {
                                Text(user.status.capitalized)
                                    .font(.makanBody(12))
                                    .foregroundStyle(user.status == "active" ? .secondary : Color.sambalRed)
                                CommunityBadge(affiliationType: user.affiliationType, university: user.university)
                            }
                        }
                    }
                }
                .transition(reduceMotion ? .opacity : .opacity.combined(with: .move(edge: .top)))
            }
        }
        .searchable(text: $searchText)
        .navigationTitle("Beta Users")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .principal) {
                Text("\(activeCount) active")
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
            }
            ToolbarItem(placement: .primaryAction) {
                Button {
                    showCreateSheet = true
                } label: {
                    Image(systemName: "plus")
                }
            }
        }
        .task { await load() }
        .refreshable { await load() }
        .sheet(isPresented: $showCreateSheet) {
            CreateBetaUserSheet(onCreated: { newUser in
                withAnimation(reduceMotion ? .easeOut(duration: 0.2) : .spring(response: 0.25, dampingFraction: 0.85)) {
                    users.insert(newUser, at: 0)
                }
            })
        }
    }

    private func handleRevoked(_ userId: Int) {
        if let index = users.firstIndex(where: { $0.id == userId }) {
            let existing = users[index]
            users[index] = AdminUser(
                id: existing.id, email: existing.email, role: existing.role, status: "revoked",
                createdAt: existing.createdAt, affiliationType: existing.affiliationType, university: existing.university
            )
        }
        BetaCredentialStore.shared.clear(id: userId)
    }

    @MainActor
    private func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await APIClient.listBetaUsers()
            users = response.users
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't load beta users."
        }
    }
}

private struct AdminUserDetailView: View {
    let user: AdminUser
    let onRevoked: (Int) -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var showRevokeConfirmation = false
    @State private var isRevoking = false
    @State private var status: String

    init(user: AdminUser, onRevoked: @escaping (Int) -> Void) {
        self.user = user
        self.onRevoked = onRevoked
        _status = State(initialValue: user.status)
    }

    var body: some View {
        List {
            Section {
                HStack {
                    Text("Email")
                    Spacer()
                    Text(user.email).foregroundStyle(.secondary)
                }
                HStack {
                    Text("Status")
                    Spacer()
                    Text(status.capitalized).foregroundStyle(.secondary)
                }
                HStack {
                    Text("Created")
                    Spacer()
                    Text(user.createdAt).foregroundStyle(.secondary)
                }
            }

            Section("Community") {
                switch user.affiliationType {
                case "university":
                    HStack {
                        Text("University")
                        Spacer()
                        Text(user.university ?? "—").foregroundStyle(.secondary)
                    }
                    Text("Verified by admin").font(.makanBody(12)).foregroundStyle(.secondary)
                case "public":
                    HStack {
                        Text("Community")
                        Spacer()
                        Text("Public").foregroundStyle(.secondary)
                    }
                    Text("Verified by admin").font(.makanBody(12)).foregroundStyle(.secondary)
                default:
                    Text("Not assigned").foregroundStyle(.secondary)
                }
            }

            if let credential = BetaCredentialStore.shared.credential(for: user.id) {
                Section("Temporary credentials") {
                    BetaCredentialsSection(email: credential.email, password: credential.password)
                }
            }

            if status == "active" {
                Section("Danger zone") {
                    Button(role: .destructive) {
                        showRevokeConfirmation = true
                    } label: {
                        if isRevoking {
                            ProgressView()
                        } else {
                            Text("Revoke access")
                        }
                    }
                    .disabled(isRevoking)
                }
            }
        }
        .navigationTitle("Beta User")
        .navigationBarTitleDisplayMode(.inline)
        .confirmationDialog(
            "Revoke beta access?",
            isPresented: $showRevokeConfirmation,
            titleVisibility: .visible
        ) {
            Button("Revoke Access", role: .destructive) {
                Task { await revoke() }
            }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("This will sign the user out on all devices and prevent them from signing in again.")
        }
    }

    @MainActor
    private func revoke() async {
        isRevoking = true
        defer { isRevoking = false }
        do {
            _ = try await APIClient.revokeBetaUser(id: user.id)
            status = "revoked"
            onRevoked(user.id)
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            // Stay on screen; user can retry from the danger zone again.
        }
    }
}

/// Threshold beyond which a segmented control stops being usable — swap to a searchable list
/// instead of squeezing more universities into horizontal segments.
private let segmentedCommunityLimit = 4

private enum CommunityLoadState: Equatable {
    case loading
    case loaded([UniversityOption])
    case failed
}

private struct CreateBetaUserSheet: View {
    let onCreated: (AdminUser) -> Void

    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var email = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var createdUser: AdminUser?
    @State private var createdPassword: String?
    @State private var communityLoadState: CommunityLoadState = .loading
    @State private var selectedCommunity = "Public"

    var body: some View {
        NavigationStack {
            Form {
                if let createdUser, let createdPassword {
                    Section("Temporary credentials") {
                        HStack {
                            Text(createdUser.email).textSelection(.enabled)
                            Spacer()
                            CommunityBadge(affiliationType: createdUser.affiliationType, university: createdUser.university)
                        }
                        BetaCredentialsSection(email: createdUser.email, password: createdPassword)
                    }
                } else {
                    Section {
                        TextField("Email", text: $email)
                            .textContentType(.emailAddress)
                            .keyboardType(.emailAddress)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .disabled(isSubmitting)
                    }

                    Section("Community") {
                        communityPicker
                        Text("This determines the user's verified university community.")
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                    }

                    if let errorMessage {
                        Text(errorMessage).foregroundStyle(Color.sambalRed)
                    }

                    Button {
                        Task { await create() }
                    } label: {
                        if isSubmitting {
                            ProgressView()
                        } else {
                            Text("Create account")
                        }
                    }
                    .disabled(isSubmitting || email.isEmpty || communityLoadState == .loading)
                }
            }
            .navigationTitle("Create Beta Account")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(createdUser == nil ? "Cancel" : "Done") { dismiss() }
                }
            }
            .task { await loadCommunities() }
        }
    }

    @ViewBuilder
    private var communityPicker: some View {
        switch communityLoadState {
        case .loading:
            HStack {
                ProgressView()
                Text("Loading communities…").foregroundStyle(.secondary)
            }
        case .failed:
            VStack(alignment: .leading, spacing: 6) {
                Text("Couldn't load communities").foregroundStyle(Color.sambalRed)
                Button("Retry") { Task { await loadCommunities() } }
            }
        case .loaded(let universities):
            if universities.count + 1 <= segmentedCommunityLimit {
                Picker("Community", selection: $selectedCommunity) {
                    Text("Public").tag("Public")
                    ForEach(universities) { university in
                        Text(university.shortName).tag(university.shortName)
                    }
                }
                .pickerStyle(.segmented)
                .disabled(isSubmitting)
                .accessibilityLabel("Community, \(selectedCommunity), selected")
            } else {
                NavigationLink {
                    CommunitySelectionList(universities: universities, selection: $selectedCommunity)
                } label: {
                    HStack {
                        Text("Community")
                        Spacer()
                        Text(selectedCommunity).foregroundStyle(.secondary)
                    }
                }
                .disabled(isSubmitting)
            }
        }
    }

    @MainActor
    private func loadCommunities() async {
        communityLoadState = .loading
        do {
            let response = try await APIClient.listUniversities()
            communityLoadState = .loaded(response.universities)
        } catch {
            communityLoadState = .failed
        }
    }

    @MainActor
    private func create() async {
        isSubmitting = true
        defer { isSubmitting = false }
        let university = selectedCommunity == "Public" ? nil : selectedCommunity
        do {
            let response = try await APIClient.createBetaUser(email: email, university: university)
            createdUser = response.user
            createdPassword = response.temporaryPassword
            BetaCredentialStore.shared.save(id: response.user.id, email: response.user.email, password: response.temporaryPassword)
            onCreated(response.user)
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't create account. Check the email and try again."
        }
    }
}

private struct CommunitySelectionList: View {
    let universities: [UniversityOption]
    @Binding var selection: String
    @Environment(\.dismiss) private var dismiss
    @State private var searchText = ""

    private var filtered: [UniversityOption] {
        guard !searchText.isEmpty else { return universities }
        return universities.filter { $0.name.localizedCaseInsensitiveContains(searchText) || $0.shortName.localizedCaseInsensitiveContains(searchText) }
    }

    var body: some View {
        List {
            Button {
                selection = "Public"
                dismiss()
            } label: {
                HStack {
                    Text("Public")
                    Spacer()
                    if selection == "Public" {
                        Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                    }
                }
            }
            ForEach(filtered) { university in
                Button {
                    selection = university.shortName
                    dismiss()
                } label: {
                    HStack {
                        Text(university.name)
                        Spacer()
                        if selection == university.shortName {
                            Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                        }
                    }
                }
            }
        }
        .searchable(text: $searchText)
        .navigationTitle("Community")
        .navigationBarTitleDisplayMode(.inline)
    }
}

private struct BetaCredentialsSection: View {
    let email: String
    let password: String

    var body: some View {
        LabeledContent("Email") {
            Text(email).textSelection(.enabled)
        }
        LabeledContent("Password") {
            Text(password).textSelection(.enabled)
        }
        Button("Copy email") {
            UIPasteboard.general.string = email
        }
        Button("Copy password") {
            UIPasteboard.general.string = password
        }
        Button("Copy both") {
            UIPasteboard.general.string = "Email: \(email)\nTemporary password: \(password)"
        }
    }
}
