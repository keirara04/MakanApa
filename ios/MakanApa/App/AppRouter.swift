import Observation

enum Route: Hashable {
    case soloPreferences
    case soloResult
}

@Observable
final class AppRouter {
    var path: [Route] = []

    func push(_ route: Route) {
        path.append(route)
    }

    func popToRoot() {
        path.removeAll()
    }
}
