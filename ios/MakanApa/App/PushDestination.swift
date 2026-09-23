import Foundation

/// Typed deep-link target parsed from a tapped push notification's payload `type` field. One
/// case per v1 notification category (see `NotificationCategory` on the backend) — keeps
/// `AppRouter`/`MakanApaApp` working with a typed value instead of a loose payload dictionary.
enum PushDestination: Equatable {
    case submission(id: Int)
    case release(version: String?)
    case account
    case communityPost(id: Int)

    init?(userInfo: [AnyHashable: Any]) {
        guard let type = userInfo["type"] as? String else { return nil }

        switch type {
        case "submission_decided":
            guard let id = (userInfo["submissionId"] as? NSNumber)?.intValue else { return nil }
            self = .submission(id: id)
        case "release":
            self = .release(version: userInfo["version"] as? String)
        case "account_admin":
            self = .account
        case "community_post_replied", "community_post_reacted":
            guard let id = (userInfo["postId"] as? NSNumber)?.intValue else { return nil }
            self = .communityPost(id: id)
        default:
            return nil
        }
    }
}

/// Written to by `PushNotificationDelegate` when a notification is tapped, observed by
/// `MakanApaApp` to route. Kept dumb on purpose — the delegate only reports "this destination was
/// tapped"; deciding what that means for navigation lives in SwiftUI, not UIKit.
@MainActor
@Observable
final class PendingDeepLink {
    static let shared = PendingDeepLink()

    var destination: PushDestination?
    /// Set when a community reply/reaction push is tapped; `CommunityView` opens that thread
    /// and clears it.
    var communityPostId: Int?

    private init() {}
}
