import SwiftUI
import UIKit

struct LocationPermissionView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService

    var body: some View {
        VStack(spacing: 24) {
            MakanApaTopBar()

            Spacer().frame(height: 40)

            switch locationService.state {
            case .denied:
                MascotView(mood: .sad, caption: Copy.locationDenied)
                MakanPrimaryButton(title: "Open Settings") {
                    if let url = URL(string: UIApplication.openSettingsURLString) {
                        UIApplication.shared.open(url)
                    }
                }
                .padding(.horizontal, 48)
            case .unavailable:
                MascotView(mood: .sad, caption: Copy.genericAPIErrorDetail)
                MakanPrimaryButton(title: "Try Again") {
                    locationService.requestLocation()
                }
                .padding(.horizontal, 48)
            default:
                MascotView(mood: .idle, caption: "Where you at?\nMakanApa uses your location to find makan nearby.", size: 88)
                MakanPrimaryButton(title: "Find makan near me") {
                    locationService.requestLocation()
                }
                .padding(.horizontal, 48)
                Text(Copy.locationPrivacyLine)
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 32)
            }

            Spacer()
        }
        .padding()
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
        .onChange(of: locationService.state) { _, newState in
            if case .authorized = newState {
                router.push(.soloPreferences)
            }
        }
        .onAppear {
            if case .authorized = locationService.state {
                router.push(.soloPreferences)
            }
        }
    }
}
