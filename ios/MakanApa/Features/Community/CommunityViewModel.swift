import CoreLocation
import Observation

@MainActor
@Observable
final class CommunityViewModel {
    private(set) var feed: CommunityFeedResponse?
    var isLoading = false
    var apiError: APIError?

    var isUniversityUser: Bool {
        if case .authenticated(let user) = AuthStore.shared.session {
            return user.affiliationType == "university"
        }
        return false
    }

    @MainActor
    func load(coordinate: CLLocationCoordinate2D?) async {
        isLoading = true
        apiError = nil
        defer { isLoading = false }
        do {
            feed = try await APIClient.communityFeed(latitude: coordinate?.latitude, longitude: coordinate?.longitude)
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch let error as APIError {
            apiError = error
        } catch {
            apiError = .transport(error)
        }
    }
}
