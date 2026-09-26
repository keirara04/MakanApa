import SwiftUI

/// Attaches every sheet/dialog a post row can trigger to one screen. Each screen that shows
/// posts owns its own `CommunityPostInteractions` and applies this once at its root, so the
/// presentations always come from the visible screen (never from one lower in the stack).
struct CommunityPostPresentations: ViewModifier {
    @Bindable var interactions: CommunityPostInteractions
    let store: CommunityPostStore
    /// Lets a thread keep its own local copy in step (removals, new replies).
    var onRemoved: (CommunityPost) -> Void = { _ in }
    var onBlockedAuthor: (Int) -> Void = { _ in }
    var onPosted: (CommunityPost) -> Void = { _ in }

    func body(content: Content) -> some View {
        content
            .environment(interactions)
            .sheet(isPresented: $interactions.isComposingNew) {
                AccountRequired(feature: "post in the community") {
                    CommunityComposerView(store: store, parent: nil, onPosted: onPosted)
                }
            }
            .sheet(item: $interactions.composerParent) { parent in
                AccountRequired(feature: "reply") {
                    CommunityComposerView(store: store, parent: parent, onPosted: onPosted)
                }
            }
            .accountSignInSheet(isPresented: $interactions.isAccountPromptPresented, source: "feature:react")
            .sheet(item: $interactions.reportTarget) { post in
                CommunityReportSheet(post: post, store: store) {
                    onRemoved(post)
                    showToast(Copy.communityPostsReportedToast)
                }
                .presentationDetents([.medium, .large])
            }
            .sheet(item: $interactions.placeTarget) { place in
                CommunityRestaurantDetailSheet(item: CommunityFeedItem(taggedPlace: place))
                    .presentationDetents([.medium, .large])
                    .presentationDragIndicator(.visible)
            }
            .confirmationDialog(
                interactions.blockTarget.map { String(format: Copy.communityPostsBlockTitleFormat, $0.author.name) } ?? "",
                isPresented: Binding(get: { interactions.blockTarget != nil }, set: { if !$0 { interactions.blockTarget = nil } }),
                titleVisibility: .visible,
                presenting: interactions.blockTarget
            ) { post in
                Button("Block", role: .destructive) {
                    Task {
                        if await store.block(authorOf: post), let authorId = post.author.id {
                            onBlockedAuthor(authorId)
                        }
                    }
                }
            } message: { _ in
                Text(Copy.communityPostsBlockDetail)
            }
            .confirmationDialog(
                Copy.communityPostsDeleteTitle,
                isPresented: Binding(get: { interactions.deleteTarget != nil }, set: { if !$0 { interactions.deleteTarget = nil } }),
                titleVisibility: .visible,
                presenting: interactions.deleteTarget
            ) { post in
                Button("Delete", role: .destructive) {
                    Task {
                        if await store.delete(post) { onRemoved(post) }
                    }
                }
            }
            .overlay(alignment: .bottom) {
                if let toast = interactions.toast {
                    Text(toast)
                        .font(.makanBody(13))
                        .foregroundStyle(.white)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 16)
                        .padding(.vertical, 12)
                        .background(Color.kicap.opacity(0.92))
                        .clipShape(RoundedRectangle(cornerRadius: 14))
                        .padding(.horizontal, 24)
                        .padding(.bottom, 16)
                        .transition(.move(edge: .bottom).combined(with: .opacity))
                        .accessibilityAddTraits(.isStaticText)
                }
            }
            .animation(.easeOut(duration: 0.2), value: interactions.toast)
    }

    private func showToast(_ message: String) {
        interactions.toast = message
        UIAccessibility.post(notification: .announcement, argument: message)
        Task {
            try? await Task.sleep(for: .seconds(3))
            if interactions.toast == message { interactions.toast = nil }
        }
    }
}

extension View {
    func communityPostPresentations(
        _ interactions: CommunityPostInteractions,
        store: CommunityPostStore,
        onRemoved: @escaping (CommunityPost) -> Void = { _ in },
        onBlockedAuthor: @escaping (Int) -> Void = { _ in },
        onPosted: @escaping (CommunityPost) -> Void = { _ in }
    ) -> some View {
        modifier(CommunityPostPresentations(
            interactions: interactions, store: store,
            onRemoved: onRemoved, onBlockedAuthor: onBlockedAuthor, onPosted: onPosted
        ))
    }
}

extension CommunityPostPlace: Identifiable {}

extension CommunityFeedItem {
    /// The detail sheet only needs id/name up front — it loads the rest via placeDetails.
    init(taggedPlace place: CommunityPostPlace) {
        self.init(
            id: place.id, name: place.name, foodCategory: place.foodCategory, rating: nil, priceLevel: nil,
            cuisines: [], openStatus: "unknown", distanceKm: nil, pickCount: nil, pickerCount: nil,
            trendingVibe: nil, approvedAt: nil
        )
    }
}
