import SwiftUI
import UIKit

struct LocationPermissionView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService

    // Guards both auto-advance triggers below so they only fire once per authorization —
    // without it, popping back from Preferences re-triggers onAppear (or a background
    // location fix changes .authorized's associated coordinate and re-triggers onChange),
    // immediately re-pushing .soloPreferences and making the back button look dead.
    @State private var didAutoAdvance = false

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
                    didAutoAdvance = false
                    locationService.requestLocation()
                }
                .padding(.horizontal, 48)
            default:
                ZStack {
                    Image("LocationMap")
                        .resizable()
                        .scaledToFit()
                        .opacity(0.18)
                        .accessibilityHidden(true)
                    Image("MascotLocation")
                        .resizable()
                        .scaledToFit()
                        .accessibilityHidden(true)
                }
                .frame(width: 130, height: 130)

                Text("Where you at?\nMakanApa uses your location to find makan nearby.")
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)

                MakanPrimaryButton(title: "Continue") {
                    didAutoAdvance = false
                    locationService.requestLocation()
                }
                .padding(.horizontal, 48)

                HStack(spacing: 24) {
                    trustBadge(icon: "shield.checkered", label: Copy.trustBadgeWhileUsing)
                    trustBadge(icon: "lock.fill", label: Copy.trustBadgePrivacy)
                    trustBadge(icon: "mappin.circle", label: Copy.trustBadgeNearby)
                }
                .padding(.top, 4)

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
            guard !didAutoAdvance, case .authorized = newState else { return }
            didAutoAdvance = true
            router.push(.soloPreferences)
        }
        .onAppear {
            guard !didAutoAdvance, case .authorized = locationService.state else { return }
            didAutoAdvance = true
            router.push(.soloPreferences)
        }
    }

    @ViewBuilder
    private func trustBadge(icon: String, label: String) -> some View {
        VStack(spacing: 4) {
            Image(systemName: icon)
                .font(.system(size: 16))
            Text(label)
                .font(.makanBody(10))
        }
        .foregroundStyle(Color.kicap.opacity(0.6))
    }
}
