import SwiftUI

@main
struct MakanApaApp: App {
    @State private var router = AppRouter()
    @State private var soloViewModel = SoloViewModel()

    var body: some Scene {
        WindowGroup {
            NavigationStack(path: $router.path) {
                HomeView()
                    .navigationDestination(for: Route.self) { route in
                        switch route {
                        case .soloPreferences:
                            PreferenceView()
                        case .soloResult:
                            ResultView()
                        }
                    }
            }
            .environment(router)
            .environment(soloViewModel)
        }
    }
}
