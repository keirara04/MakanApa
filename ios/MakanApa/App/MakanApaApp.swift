import GoogleMaps
import GoogleSignIn
import SwiftUI

@main
struct MakanApaApp: App {
    @State private var decideRouter = AppRouter()
    @State private var nearbyRouter = AppRouter()
    @State private var soloViewModel = SoloViewModel()
    @State private var locationService = LocationService()
    private var authStore = AuthStore.shared

    init() {
        GMSServices.provideAPIKey(MapsConfig.apiKey)
    }

    /// Google Maps SDK's first `GMSMapView` allocation on a cold app launch pays a one-time
    /// cost (Metal pipeline, tile renderer, font atlas setup) — normally paid exactly when the
    /// user taps the Nearby tab, which reads as a stutter. Paying it here instead, while
    /// they're still looking at Home, means Nearby's first real map view reuses warm SDK state.
    private func warmUpGoogleMaps() {
        Task.detached(priority: .utility) {
            await MainActor.run {
                _ = GMSMapView(frame: .zero)
            }
        }
    }

    var body: some Scene {
        WindowGroup {
            Group {
                switch authStore.session {
                case .loading:
                    splashView
                case .unauthenticated:
                    LoginView()
                        .transition(.opacity.combined(with: .scale(scale: 0.985)))
                case .authenticated:
                    appShell
                        .transition(.opacity.combined(with: .scale(scale: 0.985)))
                }
            }
            .animation(Motion.standard, value: authStore.session)
            .task {
                await authStore.bootstrap()
            }
            .onOpenURL { url in
                // Google's sign-in sheet completes via a redirect back into the app through the
                // reversed-client-id URL scheme registered in Info.plist — the SDK needs this
                // callback to resolve the in-flight sign-in Task, otherwise it hangs forever.
                GIDSignIn.sharedInstance.handle(url)
            }
        }
    }

    private var splashView: some View {
        VStack {
            Spacer()
            MascotView(mood: .idle, size: 120)
            Spacer()
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream)
    }

    private var appShell: some View {
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

            NavigationStack {
                CommunityView()
            }
            .tabItem {
                Label("Community", systemImage: "person.3.fill")
            }
        }
        .environment(soloViewModel)
        .environment(locationService)
        .tint(.sambalRed)
        .preferredColorScheme(.light)
        .task {
            // First-time users get the system location prompt immediately on launch,
            // instead of only after tapping into the Solo flow — reduces drop-off from
            // people never realizing the app needs it.
            if case .notDetermined = locationService.state {
                locationService.requestLocation()
            }
            warmUpGoogleMaps()
        }
    }
}
