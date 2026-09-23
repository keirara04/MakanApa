import XCTest
@testable import MakanApa

/// Pins the iOS community-post models to CommunityPostPresenter's payload
/// (api/app/Services/Community/CommunityPostPresenter.php) and covers the optimistic reaction math.
final class CommunityPostTests: XCTestCase {
    private let feedJSON = """
    {"posts":[{"id":10,"parentId":null,"body":"Nasi lemak 🔥","createdAt":"2026-09-25T03:20:00+00:00",
               "author":{"id":3,"name":"Aina","avatarKey":"roti"},"isMine":false,
               "restaurant":{"id":5,"name":"Warung Pak Ali","foodCategory":"Malay"},
               "reactionCount":3,"reactions":{"fire":2,"up":1},"myReaction":"fire","replyCount":4,
               "replies":[{"id":12,"parentId":10,"body":"Agree","createdAt":"2026-09-25T04:00:00+00:00",
                           "author":{"id":null,"name":"Deleted user","avatarKey":null},"isMine":true,"restaurant":null,
                           "reactionCount":0,"reactions":{},"myReaction":null,"replyCount":0}]}],
     "nextCursor":"eyJpZCI6MTB9","canPost":true,"cannotPostReason":null}
    """

    func testFeedPayloadDecodes() throws {
        let response = try JSONDecoder().decode(CommunityPostsResponse.self, from: Data(feedJSON.utf8))
        let post = try XCTUnwrap(response.posts.first)

        XCTAssertTrue(response.canPost)
        XCTAssertEqual(response.nextCursor, "eyJpZCI6MTB9")
        XCTAssertEqual(post.myReaction, .fire)
        XCTAssertEqual(post.count(for: .fire), 2)
        XCTAssertEqual(post.count(for: .drool), 0)
        XCTAssertEqual(post.restaurant?.name, "Warung Pak Ali")
        XCTAssertNotNil(post.createdDate)
        XCTAssertEqual(post.replies?.first?.author.id, nil)
        XCTAssertEqual(post.replies?.first?.reactions, [:])
    }

    func testReactionToggleSwitchAndRemove() throws {
        let post = try XCTUnwrap(JSONDecoder().decode(CommunityPostsResponse.self, from: Data(feedJSON.utf8)).posts.first)

        let switched = CommunityPostStore.applyingReaction(.drool, to: post)
        XCTAssertEqual(switched.myReaction, .drool)
        XCTAssertEqual(switched.count(for: .fire), 1)
        XCTAssertEqual(switched.count(for: .drool), 1)
        XCTAssertEqual(switched.reactionCount, 3)

        let removed = CommunityPostStore.applyingReaction(.drool, to: switched)
        XCTAssertNil(removed.myReaction)
        XCTAssertEqual(removed.count(for: .drool), 0)
        XCTAssertEqual(removed.reactionCount, 2)

        let added = CommunityPostStore.applyingReaction(.up, to: removed)
        XCTAssertEqual(added.myReaction, .up)
        XCTAssertEqual(added.count(for: .up), 2)
        XCTAssertEqual(added.reactionCount, 3)
    }

    func testNotificationPreferencesTolerateOlderPayload() throws {
        let json = #"{"community_submissions":true,"account_admin":true,"release_announcements":false}"#
        let preferences = try JSONDecoder().decode(NotificationPreferences.self, from: Data(json.utf8))

        XCTAssertTrue(preferences.communityReplies)
        XCTAssertFalse(preferences.communityReactions)
    }

    func testCommunityPushPayloadsRouteToThread() {
        XCTAssertEqual(DeepLinkDestination(userInfo: ["type": "community_post_replied", "postId": NSNumber(value: 42)]), .communityPost(id: 42))
        XCTAssertEqual(DeepLinkDestination(userInfo: ["type": "community_post_reacted", "postId": NSNumber(value: 7)]), .communityPost(id: 7))
        XCTAssertNil(DeepLinkDestination(userInfo: ["type": "community_post_replied"]))
    }
}
