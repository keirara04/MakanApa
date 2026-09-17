import SwiftUI
import UIKit

struct AdminUsersView: View {
    @State private var users: [AdminUser] = []
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var searchText = ""
    @State private var showCreateSheet = false

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
                            Text(user.status.capitalized)
                                .font(.makanBody(12))
                                .foregroundStyle(user.status == "active" ? .secondary : Color.sambalRed)
                        }
                    }
                }
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
                users.insert(newUser, at: 0)
            })
        }
    }

    private func handleRevoked(_ userId: Int) {
        if let index = users.firstIndex(where: { $0.id == userId }) {
            users[index] = AdminUser(id: users[index].id, email: users[index].email, role: users[index].role, status: "revoked", createdAt: users[index].createdAt)
        }
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

private struct CreateBetaUserSheet: View {
    let onCreated: (AdminUser) -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var email = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var createdCredentials: (email: String, password: String)?

    var body: some View {
        NavigationStack {
            Form {
                if let createdCredentials {
                    Section("Temporary credentials") {
                        LabeledContent("Email", value: createdCredentials.email)
                        LabeledContent("Password", value: createdCredentials.password)
                        Button("Copy credentials") {
                            UIPasteboard.general.string = "Email: \(createdCredentials.email)\nTemporary password: \(createdCredentials.password)"
                        }
                    }
                } else {
                    Section {
                        TextField("Email", text: $email)
                            .textContentType(.emailAddress)
                            .keyboardType(.emailAddress)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
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
                    .disabled(isSubmitting || email.isEmpty)
                }
            }
            .navigationTitle("Create Beta Account")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(createdCredentials == nil ? "Cancel" : "Done") { dismiss() }
                }
            }
        }
    }

    @MainActor
    private func create() async {
        isSubmitting = true
        defer { isSubmitting = false }
        do {
            let response = try await APIClient.createBetaUser(email: email)
            createdCredentials = (response.user.email, response.temporaryPassword)
            onCreated(response.user)
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't create account. Check the email and try again."
        }
    }
}
