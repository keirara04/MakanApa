import Foundation
import Observation

/// One source of truth for the community board, shared by the Community tab's preview section,
/// the full `CommunityPostsView` feed and any open thread — so a reaction, reply or delete in one
/// place is reflected everywhere without a refetch.
@MainActor
@Observable
final class CommunityPostStore {
    private(set) var posts: [CommunityPost] = []
    private(set) var nextCursor: String?
    private(set) var canPost = false
    private(set) var cannotPostReason: String?
    private(set) var hasLoaded = false
    var isLoading = false
    var isLoadingMore = false
    var apiError: APIError?

    // MARK: - Loading

    func load() async {
        isLoading = true
        apiError = nil
        defer { isLoading = false }
        do {
            let response = try await APIClient.communityPosts()
            posts = response.posts
            nextCursor = response.nextCursor
            canPost = response.canPost
            cannotPostReason = response.cannotPostReason
            hasLoaded = true
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch let error as APIError {
            apiError = error
        } catch {
            apiError = .transport(error)
        }
    }

    func loadMoreIfNeeded(after post: CommunityPost) async {
        guard post.id == posts.last?.id, let cursor = nextCursor, !isLoadingMore else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        guard let response = try? await APIClient.communityPosts(cursor: cursor) else { return }
        let known = Set(posts.map(\.id))
        posts.append(contentsOf: response.posts.filter { !known.contains($0.id) })
        nextCursor = response.nextCursor
    }

    // MARK: - Writes

    /// Returns the created post. Throws so the composer can show the server's rejection message
    /// (content filter, account too new) inline.
    @discardableResult
    func create(body: String, restaurantId: Int?, parentId: Int?) async throws -> CommunityPost {
        let post = try await APIClient.createCommunityPost(body: body, restaurantId: restaurantId, parentId: parentId).post

        if let parentId {
            mutate(parentId) { parent in
                parent.replyCount += 1
                var preview = parent.replies ?? []
                preview.append(post)
                parent.replies = Array(preview.suffix(2))
            }
        } else {
            posts.insert(post, at: 0)
        }
        return post
    }

    /// Optimistic: the tap updates immediately, then the server's tallies overwrite the guess.
    /// Returns the post's final state, or nil when the request failed (and the guess was rolled back).
    @discardableResult
    func react(_ post: CommunityPost, with type: CommunityReactionType) async -> CommunityPost? {
        let before = find(post.id) ?? post
        mutate(post.id) { $0 = CommunityPostStore.applyingReaction(type, to: $0) }

        do {
            let response = try await APIClient.reactToCommunityPost(id: post.id, type: type)
            var settled = before
            settled.myReaction = response.myReaction
            settled.reactionCount = response.reactionCount
            settled.reactions = response.reactions
            mutate(post.id) { updated in
                updated.myReaction = settled.myReaction
                updated.reactionCount = settled.reactionCount
                updated.reactions = settled.reactions
            }
            return settled
        } catch {
            mutate(post.id) { $0 = before }
            return nil
        }
    }

    func delete(_ post: CommunityPost) async -> Bool {
        guard (try? await APIClient.deleteCommunityPost(id: post.id)) != nil else { return false }
        remove(post)
        return true
    }

    func report(_ post: CommunityPost, reason: CommunityReportReason, note: String?) async throws {
        _ = try await APIClient.reportCommunityPost(id: post.id, reason: reason, note: note)
        // Hide it locally straight away — the reporter shouldn't have to keep looking at it,
        // whether or not it has reached the server-side auto-hide threshold yet.
        remove(post)
    }

    func block(authorOf post: CommunityPost) async -> Bool {
        guard let authorId = post.author.id,
              (try? await APIClient.blockUser(id: authorId)) != nil else { return false }
        removeAll(byAuthor: authorId)
        return true
    }

    /// Keeps the store in step with changes made inside an open thread.
    func apply(threadParent: CommunityPost) {
        mutate(threadParent.id) { existing in
            let preview = existing.replies
            existing = threadParent
            existing.replies = preview
        }
    }

    func find(_ id: Int) -> CommunityPost? {
        for post in posts {
            if post.id == id { return post }
            if let reply = post.replies?.first(where: { $0.id == id }) { return reply }
        }
        return nil
    }

    // MARK: - Helpers

    nonisolated static func applyingReaction(_ type: CommunityReactionType, to post: CommunityPost) -> CommunityPost {
        var post = post
        if let current = post.myReaction {
            post.reactions[current.rawValue] = max(0, post.count(for: current) - 1)
            post.reactionCount = max(0, post.reactionCount - 1)
        }
        if post.myReaction == type {
            post.myReaction = nil
        } else {
            post.myReaction = type
            post.reactions[type.rawValue] = post.count(for: type) + 1
            post.reactionCount += 1
        }
        return post
    }

    private func mutate(_ id: Int, _ change: (inout CommunityPost) -> Void) {
        for index in posts.indices {
            if posts[index].id == id {
                change(&posts[index])
                return
            }
            if let replyIndex = posts[index].replies?.firstIndex(where: { $0.id == id }) {
                change(&posts[index].replies![replyIndex])
                return
            }
        }
    }

    private func remove(_ post: CommunityPost) {
        if let parentId = post.parentId {
            mutate(parentId) { parent in
                parent.replies?.removeAll { $0.id == post.id }
                parent.replyCount = max(0, parent.replyCount - 1)
            }
        } else {
            posts.removeAll { $0.id == post.id }
        }
    }

    private func removeAll(byAuthor authorId: Int) {
        posts.removeAll { $0.author.id == authorId }
        for index in posts.indices {
            let before = posts[index].replies?.count ?? 0
            posts[index].replies?.removeAll { $0.author.id == authorId }
            posts[index].replyCount = max(0, posts[index].replyCount - (before - (posts[index].replies?.count ?? 0)))
        }
    }
}

extension CommunityPost {
    /// Navigation stand-in for a thread opened by id alone (e.g. from a push) — the thread view
    /// only reads `id` and loads everything else itself.
    static func placeholder(id: Int) -> CommunityPost {
        CommunityPost(
            id: id, parentId: nil, body: "", createdAt: "", author: CommunityPostAuthor(id: nil, name: "", avatarKey: nil),
            isMine: false, restaurant: nil, reactionCount: 0, reactions: [:], myReaction: nil, replyCount: 0, replies: nil
        )
    }
}
