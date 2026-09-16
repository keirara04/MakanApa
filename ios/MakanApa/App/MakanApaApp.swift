import SwiftUI

@main
struct MakanApaApp: App {
    @State private var router = AppRouter()
    @State private var soloViewModel = SoloViewModel()
    @State private var locationService = LocationService()

    var body: some Scene {
        WindowGroup {
            NavigationStack(path: $router.path) {
                HomeView()
                    .navigationDestination(for: Route.self) { route in
                        switch route {
                        case .locationPermission:
                            LocationPermissionView()
                        case .soloPreferences:
                            PreferenceView()
                        case .soloResult:
                            ResultView()
                        }
                    }
            }
            .environment(router)
            .environment(soloViewModel)
            .environment(locationService)
            .tint(.sambalRed)
            .preferredColorScheme(.light)
        }
    }
}
