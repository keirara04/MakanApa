import GoogleMaps
import GoogleSignIn
import SwiftUI

enum AppTab {
    case decide, nearby, community
}

@main
struct MakanApaApp: App {
    @UIApplicationDelegateAdaptor(PushNotificationDelegate.self) private var pushDelegate
    @State private var decideRouter = AppRouter()
    @State private var nearbyRouter = AppRouter()
    @State private var soloViewModel = SoloViewModel()
    @State private var locationService = LocationService()
    @State private var onboardingState = OnboardingState.shared
    @State private var pendingDeepLink = PendingDeepLink.shared
    @State private var selectedTab: AppTab = .decide
    private var authStore = AuthStore.shared
    @Environment(\.scenePhase) private var scenePhase

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
                if !onboardingState.hasCompletedOnboarding {
                    OnboardingView(onFinished: {})
                        .environment(locationService)
                        .transition(.opacity.combined(with: .scale(scale: 0.985)))
                } else {
                    // Notification priming is no longer a launch gate — it's offered after the
                    // user's first accepted pick (ResultView.afterAccept()).
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
            }
            .animation(Motion.standard, value: authStore.session)
            .animation(Motion.standard, value: onboardingState.hasCompletedOnboarding)
            .task {
                await authStore.bootstrap()
            }
            .onOpenURL { url in
                // makanapa://place/… from a shared page's "Open in MakanApa" button.
                if let destination = DeepLinkDestination(url: url) {
                    pendingDeepLink.destination = destination
                    return
                }
                // Google's sign-in sheet completes via a redirect back into the app through the
                // reversed-client-id URL scheme registered in Info.plist — the SDK needs this
                // callback to resolve the in-flight sign-in Task, otherwise it hangs forever.
                GIDSignIn.sharedInstance.handle(url)
            }
            .onContinueUserActivity(NSUserActivityTypeBrowsingWeb) { activity in
                // A shared https://…/p/{id} link tapped in WhatsApp etc. (universal link).
                guard let url = activity.webpageURL, let destination = DeepLinkDestination(url: url) else { return }
                pendingDeepLink.destination = destination
            }
            .onChange(of: scenePhase) { _, newPhase in
                // Only tracked once actually signed in — a logged-out person opening/closing
                // the app has no user_id for the admin dashboard's "App opens" chart to attach
                // to. .background specifically, not .inactive, so a transient system alert or
                // the app switcher swipe-up preview doesn't register as a close.
                guard case .authenticated = authStore.session else { return }

                switch newPhase {
                case .active:
                    AppSessionTracker.shared.start()
                case .background:
                    AppSessionTracker.shared.end()
                default:
                    break
                }
            }
            .onChange(of: authStore.session) { _, newSession in
                // Covers the cold-launch case above: scenePhase is already .active by the time
                // bootstrap()/login resolves to .authenticated, so the scenePhase-only handler
                // above never fires for that very first session of the app run.
                guard case .authenticated = newSession, scenePhase == .active else { return }
                AppSessionTracker.shared.start()
            }
            .onChange(of: pendingDeepLink.destination) { _, destination in
                guard let destination else { return }
                handle(destination)
                pendingDeepLink.destination = nil
            }
        }
    }

    /// The delegate only reports which destination was tapped — this is the one place that
    /// decides what it means for navigation. No per-submission detail screen exists yet, so
    /// `.submission` lands on the Community tab (My Submissions is reachable from there) rather
    /// than a specific detail push.
    private func handle(_ destination: DeepLinkDestination) {
        switch destination {
        case .submission:
            selectedTab = .community
        case .communityPost(let id):
            selectedTab = .community
            pendingDeepLink.communityPostId = id
        case .place(let id, let source):
            // One path for every entry point: Nearby opens the place's sheet (see openPlace).
            selectedTab = .nearby
            pendingDeepLink.placeRequest = PlaceOpenRequest(restaurantId: id, source: source)
        case .mealNudge(let nudgeId, let restaurantId):
            Task { _ = try? await APIClient.nudgeEvent(nudgeId: nudgeId, event: "opened") }
            if let restaurantId {
                selectedTab = .nearby
                pendingDeepLink.placeRequest = PlaceOpenRequest(restaurantId: restaurantId, source: .nudge, nudgeId: nudgeId)
            } else {
                // Generic nudge: straight into a one-tap pick.
                selectedTab = .decide
                decideRouter.popToRoot()
                pendingDeepLink.quickPickRequest = QuickPickRequest(nudgeId: nudgeId)
            }
        case .quickPick(let nudgeId):
            selectedTab = .decide
            decideRouter.popToRoot()
            pendingDeepLink.quickPickRequest = QuickPickRequest(nudgeId: nudgeId)
        case .release, .account, .decision, .gengRoom:
            // decision / gengRoom are reserved until those screens exist.
            selectedTab = .decide
        }
    }

    private var splashView: some View {
        VStack(spacing: 16) {
            Spacer()
            MascotView(mood: authStore.bootstrapFailed ? .sad : .idle, size: 120)
            // Only reachable offline on a first launch with a saved token but no cached profile —
            // the token is kept, so a retry lands straight back in the app.
            if authStore.bootstrapFailed {
                Text(Copy.connectionErrorDetail)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
                Button(Copy.tryAgain) {
                    Task { await authStore.bootstrap() }
                }
                .font(.makanBody(15))
                .foregroundStyle(Color.sambalRed)
            }
            Spacer()
        }
        .padding(.horizontal, 24)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream)
    }

    private var appShell: some View {
        TabView(selection: $selectedTab) {
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
            .tag(AppTab.decide)

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
            .tag(AppTab.nearby)

            NavigationStack {
                CommunityView()
            }
            .tabItem {
                Label("Community", systemImage: "person.3.fill")
            }
            .tag(AppTab.community)
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
