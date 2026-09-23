import SwiftUI

/// Lists people the user has blocked from community posts, with unblock. Blocking itself
/// happens from a post's "…" menu.
struct BlockedUsersView: View {
    @State private var users: [BlockedUser] = []
    @State private var isLoading = true
    @State private var loadFailed = false

    var body: some View {
        List {
            if isLoading && users.isEmpty {
                HStack { Spacer(); ProgressView(); Spacer() }
                    .listRowBackground(Color.clear)
            } else if loadFailed {
                Text(Copy.connectionErrorDetail)
                    .foregroundStyle(.secondary)
                    .listRowBackground(Color.clear)
            } else if users.isEmpty {
                Text("You haven't blocked anyone.")
                    .foregroundStyle(.secondary)
                    .listRowBackground(Color.clear)
            }

            ForEach(users) { user in
                HStack(spacing: 10) {
                    Image(AvatarCharacter(key: user.avatarKey).imageName)
                        .resizable()
                        .scaledToFill()
                        .frame(width: 32, height: 32)
                        .clipShape(Circle())
                        .accessibilityHidden(true)
                    Text(user.name)
                        .foregroundStyle(Color.kicap)
                    Spacer()
                    Button("Unblock") {
                        Task { await unblock(user) }
                    }
                    .buttonStyle(.bordered)
                    .tint(.sambalRed)
                }
            }
        }
        .navigationTitle("Blocked users")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
    }

    private func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            users = try await APIClient.blockedUsers().users
            loadFailed = false
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            loadFailed = true
        }
    }

    private func unblock(_ user: BlockedUser) async {
        guard (try? await APIClient.unblockUser(id: user.id)) != nil else { return }
        withAnimation { users.removeAll { $0.id == user.id } }
    }
}
