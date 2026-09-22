import SwiftUI

/// Pushed from Settings' Account row (not a sheet) — a full page, not a modal, since this is the
/// one place a user's identity (name, avatar, community) lives, not a quick one-off action.
struct EditProfileView: View {
    @Environment(\.dismiss) private var dismiss
    @State private var name: String
    @State private var selectedAvatar: AvatarCharacter
    @State private var isSaving = false
    @State private var errorMessage: String?
    @State private var showingCommunitySheet = false

    init() {
        let user: AuthUser? = if case .authenticated(let user) = AuthStore.shared.session { user } else { nil }
        _name = State(initialValue: user?.name ?? "")
        _selectedAvatar = State(initialValue: AvatarCharacter(key: user?.avatarKey))
    }

    private var columns: [GridItem] {
        [GridItem(.adaptive(minimum: 72), spacing: 16)]
    }

    var body: some View {
        List {
            Section {
                VStack(spacing: 8) {
                    Image(selectedAvatar.imageName)
                        .resizable()
                        .scaledToFit()
                        .frame(width: 96, height: 96)
                        .clipShape(Circle())
                    Text(name.isEmpty ? "Your name" : name)
                        .font(.system(.title3, design: .rounded, weight: .bold))
                        .foregroundStyle(name.isEmpty ? .secondary : Color.kicap)
                    if let communityLabel {
                        Text(communityLabel)
                            .font(.makanBody(13))
                            .foregroundStyle(.secondary)
                    }
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 12)
                .listRowBackground(Color.clear)
            }

            Section("Choose your character") {
                LazyVGrid(columns: columns, spacing: 16) {
                    ForEach(AvatarCharacter.allCases) { avatar in
                        Button {
                            selectedAvatar = avatar
                        } label: {
                            VStack(spacing: 4) {
                                Image(avatar.imageName)
                                    .resizable()
                                    .scaledToFit()
                                    .frame(width: 56, height: 56)
                                    .clipShape(Circle())
                                    .overlay(
                                        Circle().strokeBorder(
                                            avatar == selectedAvatar ? Color.sambalRed : .clear,
                                            lineWidth: 3
                                        )
                                    )
                                Text(avatar.displayName)
                                    .font(.makanBody(11))
                                    .foregroundStyle(.secondary)
                            }
                        }
                        .buttonStyle(.plain)
                    }
                }
                .padding(.vertical, 4)
            }

            Section("Name") {
                TextField("Your name", text: $name)
                    .foregroundStyle(Color.kicap)
            }

            Section("Community") {
                Button {
                    showingCommunitySheet = true
                } label: {
                    HStack {
                        Text(communityLabel ?? "Public")
                            .foregroundStyle(Color.kicap)
                        Spacer()
                        Image(systemName: "chevron.right")
                            .font(.system(size: 12, weight: .semibold))
                            .foregroundStyle(.secondary)
                    }
                }
            }

            if let errorMessage {
                Section {
                    Text(errorMessage).foregroundStyle(Color.sambalRed)
                }
            }
        }
        .navigationTitle("Edit Profile")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .confirmationAction) {
                if isSaving {
                    ProgressView()
                } else {
                    Button("Save") { Task { await save() } }
                }
            }
        }
        .sheet(isPresented: $showingCommunitySheet) {
            CommunityAssignmentSheet(currentUniversity: currentUniversity, currentArea: currentArea) {}
        }
    }

    /// Always derived from the live session, never copied into local state at `init` — the
    /// community sheet mutates `AuthStore.session` directly, so reading it here (instead of a
    /// snapshot taken when this view opened) means Save can never clobber a change the user just
    /// made in that sheet with stale data.
    private var currentUser: AuthUser? {
        if case .authenticated(let user) = AuthStore.shared.session { return user }
        return nil
    }

    private var currentUniversity: String? { currentUser?.university }
    private var currentArea: String? { currentUser?.area }

    private var communityLabel: String? {
        currentUniversity ?? currentArea
    }

    @MainActor
    private func save() async {
        errorMessage = nil
        isSaving = true
        defer { isSaving = false }
        do {
            try await AuthStore.shared.updateProfile(
                name: name.trimmingCharacters(in: .whitespaces),
                avatarKey: selectedAvatar.key
            )
            dismiss()
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't save your profile. Try again."
        }
    }
}

#Preview {
    NavigationStack {
        EditProfileView()
    }
}
