import SwiftUI
import GoogleMaps

@main
struct MakanApaApp: App {
    @State private var decideRouter = AppRouter()
    @State private var nearbyRouter = AppRouter()
    @State private var soloViewModel = SoloViewModel()
    @State private var locationService = LocationService()

    init() {
        GMSServices.provideAPIKey(MapsConfig.apiKey)
    }

    var body: some Scene {
        WindowGroup {
            TabView {
                NavigationStack(path: $decideRouter.path) {
                    HomeView()
                        .navigationDestination(for: Route.self) { route in
                            switch route {
                            case .locationPermission:
                                LocationPermissionView()
                            case .soloPreferences:
                                PreferenceView()
                                    .toolbar(.hidden, for: .tabBar)
                            case .soloResult:
                                ResultView()
                                    .toolbar(.hidden, for: .tabBar)
                            }
                        }
                }
                .environment(decideRouter)
                .tabItem {
                    Label("Decide", systemImage: "sparkles")
                }

                NavigationStack(path: $nearbyRouter.path) {
                    NearbyView()
                        .navigationDestination(for: Route.self) { route in
                            switch route {
                            case .soloResult:
                                ResultView()
                            default:
                                EmptyView()
                            }
                        }
                }
                .environment(nearbyRouter)
                .tabItem {
                    Label("Nearby", systemImage: "map")
                }
            }
            .environment(soloViewModel)
            .environment(locationService)
            .tint(.sambalRed)
            .preferredColorScheme(.light)
        }
    }
}
