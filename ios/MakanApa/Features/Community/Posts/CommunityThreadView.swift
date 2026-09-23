import SwiftUI

/// A post and all its replies (one level), with an inline reply bar. Keeps its own copy of the
/// thread — the shared store only holds feed pages — and pushes changes back into the store so
/// the feed card underneath is current when this is popped.
struct CommunityThreadView: View {
    let postId: Int
    let store: CommunityPostStore

    @Environment(\.dismiss) private var dismiss
    @State private var interactions = CommunityPostInteractions()
    @State private var parent: CommunityPost?
    @State private var replies: [CommunityPost] = []
    @State private var nextCursor: String?
    @State private var canReply = false
    @State private var isLoading = true
    @State private var loadFailed = false
    @State private var replyText = ""
    @State private var isSending = false
    @State private var sendError: String?
    @FocusState private var replyFocused: Bool

    var body: some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 16) {
                    if let parent {
                        CommunityPostRow(post: parent, style: .threadParent, onReact: react)

                        if !replies.isEmpty {
                            Text(parent.replyCount == 1 ? "1 REPLY" : "\(max(parent.replyCount, replies.count)) REPLIES")
                                .font(.makanBody(12))
                                .foregroundStyle(.secondary)
                                .tracking(1)
                                .padding(.top, 4)
                        }

                        ForEach(replies) { reply in
                            CommunityPostRow(post: reply, style: .reply, onReact: react)
                                .padding(.horizontal, 14)
                                .id(reply.id)
                                .task { await loadMoreIfNeeded(after: reply) }
                        }
                    } else if isLoading {
                        ProgressView().frame(maxWidth: .infinity).padding(.top, 60)
                    } else if loadFailed {
                        ContentUnavailableView(
                            "Post unavailable",
                            systemImage: "bubble.left.and.exclamationmark.bubble.right",
                            description: Text("It may have been deleted or hidden.")
                        )
                        .padding(.top, 40)
                    }
                }
                .padding(16)
            }
            .scrollDismissesKeyboard(.interactively)
            .safeAreaInset(edge: .bottom) {
                if parent != nil && canReply {
                    replyBar(proxy: proxy)
                }
            }
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .navigationTitle("Thread")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
        .communityPostPresentations(
            interactions, store: store,
            onRemoved: handleRemoved,
            onBlockedAuthor: { authorId in
                if parent?.author.id == authorId {
                    dismiss()
                } else {
                    replies.removeAll { $0.author.id == authorId }
                }
            },
            onPosted: { post in
                if post.parentId == parent?.id { appendReply(post) }
            }
        )
    }

    // MARK: - Reply bar

    private func replyBar(proxy: ScrollViewProxy) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            if let sendError {
                Text(sendError)
                    .font(.makanBody(12))
                    .foregroundStyle(Color.sambalRed)
                    .padding(.horizontal, 4)
            }
            HStack(alignment: .bottom, spacing: 8) {
                TextField(Copy.communityPostsReplyPlaceholder, text: $replyText, axis: .vertical)
                    .font(.makanBody(15))
                    .lineLimit(1...5)
                    .focused($replyFocused)
                    .padding(.horizontal, 14)
                    .padding(.vertical, 10)
                    .background(Color.white)
                    .clipShape(RoundedRectangle(cornerRadius: 20))

                Button {
                    Task { await sendReply(proxy: proxy) }
                } label: {
                    Group {
                        if isSending {
                            ProgressView().tint(.white)
                        } else {
                            Image(systemName: "arrow.up").font(.system(size: 16, weight: .bold))
                        }
                    }
                    .foregroundStyle(.white)
                    .frame(width: 40, height: 40)
                    .background(canSend ? Color.sambalRed : Color.kicap.opacity(0.2))
                    .clipShape(Circle())
                }
                .disabled(!canSend)
                .accessibilityLabel("Send reply")
            }
            if replyText.count > 250 {
                Text("\(280 - replyText.count) left")
                    .font(.makanBody(11).monospacedDigit())
                    .foregroundStyle(replyText.count > 280 ? Color.sambalRed : .secondary)
                    .padding(.horizontal, 4)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .background(.bar)
    }

    private var canSend: Bool {
        let trimmed = replyText.trimmingCharacters(in: .whitespacesAndNewlines)
        return !trimmed.isEmpty && replyText.count <= 280 && !isSending
    }

    // MARK: - Actions

    private func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await APIClient.communityThread(postId: postId)
            parent = response.post
            replies = response.replies
            nextCursor = response.nextCursor
            canReply = response.canReply
            loadFailed = false
            store.apply(threadParent: response.post)
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            if parent == nil { loadFailed = true }
        }
    }

    private func loadMoreIfNeeded(after reply: CommunityPost) async {
        guard reply.id == replies.last?.id, let cursor = nextCursor, let parent else { return }
        nextCursor = nil
        guard let response = try? await APIClient.communityThread(postId: parent.id, cursor: cursor) else {
            nextCursor = cursor
            return
        }
        let known = Set(replies.map(\.id))
        replies.append(contentsOf: response.replies.filter { !known.contains($0.id) })
        nextCursor = response.nextCursor
    }

    private func sendReply(proxy: ScrollViewProxy) async {
        guard canSend, let parent else { return }
        isSending = true
        sendError = nil
        defer { isSending = false }
        do {
            let reply = try await store.create(
                body: replyText.trimmingCharacters(in: .whitespacesAndNewlines), restaurantId: nil, parentId: parent.id
            )
            replyText = ""
            appendReply(reply)
            withAnimation { proxy.scrollTo(reply.id, anchor: .bottom) }
        } catch let error as APIError {
            sendError = error.serverMessage ?? Copy.communityPostsGenericError
        } catch {
            sendError = Copy.communityPostsGenericError
        }
    }

    private func appendReply(_ reply: CommunityPost) {
        guard !replies.contains(where: { $0.id == reply.id }) else { return }
        // Only append once every older page is loaded — otherwise it'd sit above unseen replies.
        if nextCursor == nil { replies.append(reply) }
        parent?.replyCount += 1
    }

    private func react(_ tap: CommunityPostRow.ReactionTap) {
        let original = tap.post
        update(original.id) { $0 = CommunityPostStore.applyingReaction(tap.type, to: $0) }
        Task {
            if let settled = await store.react(original, with: tap.type) {
                update(original.id) { $0 = settled }
            } else {
                update(original.id) { $0 = original }
            }
        }
    }

    private func handleRemoved(_ post: CommunityPost) {
        if post.id == parent?.id {
            dismiss()
        } else {
            replies.removeAll { $0.id == post.id }
            parent?.replyCount = max(0, (parent?.replyCount ?? 1) - 1)
        }
    }

    private func update(_ id: Int, _ change: (inout CommunityPost) -> Void) {
        if parent?.id == id, var copy = parent {
            change(&copy)
            parent = copy
        } else if let index = replies.firstIndex(where: { $0.id == id }) {
            change(&replies[index])
        }
    }
}
