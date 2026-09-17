import SwiftUI
import GoogleMaps

@main
struct MakanApaApp: App {
    @State private var decideRouter = AppRouter()
    @State private var nearbyRouter = AppRouter()
    @State private var soloViewModel = SoloViewModel()
    @State private var locationService = LocationService()
    @State private var selectedTab: AppTab = .decide
    @State private var bottomChrome = BottomChromeCoordinator()

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

    private var decideStack: some View {
        NavigationStack(path: $decideRouter.path) {
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
        .environment(decideRouter)
    }

    private var nearbyStack: some View {
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
    }

    var body: some Scene {
        WindowGroup {
            ZStack(alignment: .bottom) {
                TabView(selection: $selectedTab) {
                    decideStack.tag(AppTab.decide)
                    nearbyStack.tag(AppTab.nearby)
                }
                .toolbar(.hidden, for: .tabBar)

                FloatingTabBar(selection: $selectedTab)
                    .padding(.horizontal, 20)
                    .padding(.bottom, 10)
                    .opacity(bottomChrome.state == .resultPresented ? 0 : (bottomChrome.state == .placeSelected ? 0.72 : 1))
                    .offset(y: bottomChrome.state == .resultPresented ? 16 : 0)
                    .scaleEffect(bottomChrome.state == .resultPresented ? 0.96 : 1)
                    .allowsHitTesting(bottomChrome.state != .resultPresented)
                    .animation(Motion.standard, value: bottomChrome.state)
            }
            .environment(soloViewModel)
            .environment(locationService)
            .environment(bottomChrome)
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
            .onChange(of: selectedTab) { updateResultFlowPresented() }
            .onChange(of: decideRouter.path) { updateResultFlowPresented() }
            .onChange(of: nearbyRouter.path) { updateResultFlowPresented() }
        }
    }

    /// Single source of truth for "is a pushed route dominating the screen" — computed here since
    /// this is the one place that knows which router is currently on-screen, avoiding the need for
    /// per-view writers to guard against clobbering each other's updates.
    private func updateResultFlowPresented() {
        bottomChrome.isResultFlowPresented = selectedTab == .decide
            ? !decideRouter.path.isEmpty
            : !nearbyRouter.path.isEmpty
    }
}
