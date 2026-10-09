import Foundation

/// Where an opened place came from — carried through to "Makan sini" so every entry point is
/// attributed the same way.
enum PlaceOpenSource: String, Equatable {
    case share, nudge, search, community, recommendation, nearby
}

/// A request for Nearby to open one place's sheet (see `NearbyView`'s `openPlace`).
struct PlaceOpenRequest: Equatable {
    let restaurantId: Int
    let source: PlaceOpenSource
    /// Set when a mealtime nudge opened it — sheet actions report back to that nudge's funnel.
    var nudgeId: Int? = nil
}

/// A request for Home to run "Just pick lah" (a generic nudge, or "Pick something else").
struct QuickPickRequest: Equatable {
    let id = UUID()
    var nudgeId: Int? = nil
}

/// Every way into the app lands here as one typed value — a tapped push notification, a shared
/// universal link (`https://<marketing domain>/p/624-kfc`), or the app's own URL scheme
/// (`makanapa://place/624`). `MakanApaApp.handle(_:)` decides what each one means for navigation.
enum DeepLinkDestination: Equatable {
    case submission(id: Int)
    case release(version: String?)
    case account
    case communityPost(id: Int)
    case place(id: Int, source: PlaceOpenSource)
    /// A tapped mealtime nudge — names a place, or (generic) asks for a quick pick.
    case mealNudge(nudgeId: Int, restaurantId: Int?)
    /// Run Home's one-tap pick.
    case quickPick(nudgeId: Int?)
    /// Reserved: opening a past decision from a link.
    case decision(id: Int)
    /// Reserved: joining a Geng room from `/g/{code}`.
    case gengRoom(code: String)

    /// From a tapped push notification's payload `type` field — one case per backend
    /// `NotificationCategory`.
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
        case "meal_nudge":
            guard let id = (userInfo["nudgeId"] as? NSNumber)?.intValue else { return nil }
            self = .mealNudge(nudgeId: id, restaurantId: (userInfo["restaurantId"] as? NSNumber)?.intValue)
        default:
            return nil
        }
    }

    /// From a universal link or the `makanapa://` scheme. Returns nil for anything else (e.g.
    /// Google Sign-In's redirect), so callers can hand those on.
    init?(url: URL) {
        let parts: [String]
        if url.scheme == "makanapa" {
            // makanapa://place/624?source=share → host "place", path "/624"
            parts = [url.host ?? ""] + url.pathComponents.filter { $0 != "/" }
        } else if url.scheme == "https" {
            parts = url.pathComponents.filter { $0 != "/" }
        } else {
            return nil
        }
        guard parts.count >= 2 else { return nil }

        let source = URLComponents(url: url, resolvingAgainstBaseURL: false)?
            .queryItems?.first { $0.name == "source" || $0.name == "ref" }?.value
            .flatMap(PlaceOpenSource.init(rawValue:)) ?? .share

        switch parts[0] {
        case "p", "place":
            // "624-kfc-jalan-reko" — the id is authoritative, the slug cosmetic.
            guard let id = Int(parts[1].prefix { $0.isNumber }) else { return nil }
            self = .place(id: id, source: source)
        case "d", "decision":
            guard let id = Int(parts[1]) else { return nil }
            self = .decision(id: id)
        case "g":
            self = .gengRoom(code: parts[1])
        default:
            return nil
        }
    }
}

/// Written to by `PushNotificationDelegate` / URL handlers, observed by `MakanApaApp` to route.
/// Kept dumb on purpose — deciding what a destination means for navigation lives in SwiftUI.
@MainActor
@Observable
final class PendingDeepLink {
    static let shared = PendingDeepLink()

    var destination: DeepLinkDestination?
    /// Set when a community reply/reaction push is tapped; `CommunityView` opens that thread
    /// and clears it.
    var communityPostId: Int?
    /// Set when a shared link / nudge / other entry point wants a place opened; `NearbyView`
    /// opens its sheet and clears it.
    var placeRequest: PlaceOpenRequest?
    /// Set when something wants Home's "Just pick lah" run; `HomeView` runs it and clears it.
    var quickPickRequest: QuickPickRequest?

    private init() {}
}
