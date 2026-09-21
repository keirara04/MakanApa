import Foundation

/// Real open/close tracking for the admin dashboard's "App opens" chart — fire-and-forget,
/// never blocks or surfaces errors to the user. Distinct from `InstallationID` (anonymous,
/// permanent) and from Sanctum's own last-used-token timestamp (proves an API call happened,
/// not that a person opened/closed the app).
@MainActor
final class AppSessionTracker {
    static let shared = AppSessionTracker()

    private var currentSessionId: Int?

    private init() {}

    /// Call when the app becomes active and the user is authenticated. No-op if a session is
    /// already open (e.g. scenePhase flapping .active -> .inactive -> .active on a system
    /// interruption shouldn't open a second session).
    func start() {
        guard currentSessionId == nil else { return }

        Task {
            do {
                let response = try await APIClient.startAppSession()
                currentSessionId = response.sessionId
            } catch {
                // Analytics-only — losing one open event is never worth surfacing to the user.
            }
        }
    }

    /// Call when the app moves to the background. Clears the local id immediately so a rapid
    /// background/foreground cycle can't send two `end` calls for the same session.
    func end() {
        guard let sessionId = currentSessionId else { return }
        currentSessionId = nil

        Task {
            try? await APIClient.endAppSession(sessionId: sessionId)
        }
    }
}
