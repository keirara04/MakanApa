import SwiftUI

/// The full "What KU is saying" board — infinite scroll over the shared store.
struct CommunityPostsView: View {
    let store: CommunityPostStore
    let communityName: String

    @State private var interactions = CommunityPostInteractions()

    var body: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: 12) {
                if store.posts.isEmpty && !store.isLoading {
                    CommunityPostsEmptyState(store: store, communityName: communityName) {
                        interactions.isComposingNew = true
                    }
                } else {
                    ForEach(store.posts) { post in
                        CommunityPostRow(post: post, onReact: react)
                            .task { await store.loadMoreIfNeeded(after: post) }
                    }
                    if store.isLoadingMore {
                        ProgressView().frame(maxWidth: .infinity).padding()
                    }
                }
            }
            .padding(16)
            .padding(.bottom, 72)
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .navigationTitle(String(format: Copy.communityPostsSectionFormat, communityName).capitalized)
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await store.load() }
        .overlay(alignment: .bottomTrailing) {
            if store.canPost {
                CommunityComposeButton { interactions.isComposingNew = true }
                    .padding(20)
            }
        }
        .navigationDestination(item: $interactions.threadTarget) { post in
            CommunityThreadView(postId: post.id, store: store)
        }
        .communityPostPresentations(interactions, store: store)
    }

    private func react(_ tap: CommunityPostRow.ReactionTap) {
        Task { await store.react(tap.post, with: tap.type) }
    }
}

struct CommunityComposeButton: View {
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Label(Copy.communityPostsShareCTA, systemImage: "square.and.pencil")
                .font(.makanBody(15))
                .foregroundStyle(.white)
                .padding(.horizontal, 18)
                .frame(minHeight: 48)
                .background(Color.sambalRed)
                .clipShape(Capsule())
                .shadow(color: Color.sambalRed.opacity(0.35), radius: 10, y: 4)
        }
        .buttonStyle(.plain)
    }
}

struct CommunityPostsEmptyState: View {
    let store: CommunityPostStore
    let communityName: String
    let onCompose: () -> Void

    var body: some View {
        VStack(spacing: 10) {
            Text("💬")
                .font(.system(size: 34))
                .accessibilityHidden(true)
            Text(Copy.communityPostsEmptyHeadline)
                .font(.makanBody(15))
                .foregroundStyle(Color.kicap)
            Text(store.canPost
                 ? String(format: Copy.communityPostsEmptyDetailFormat, communityName)
                 : (store.cannotPostReason ?? Copy.communityPostsJoinPrompt))
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            if store.canPost {
                Button(action: onCompose) {
                    Text(Copy.communityPostsShareCTA)
                        .font(.makanBody(14))
                        .foregroundStyle(.white)
                        .padding(.horizontal, 20)
                        .frame(minHeight: 44)
                        .background(Color.sambalRed)
                        .clipShape(Capsule())
                }
                .buttonStyle(.plain)
                .padding(.top, 4)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 24)
        .padding(.horizontal, 16)
        .background(Color.white.opacity(0.6))
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }
}
