import SwiftUI
import UIKit

private struct PressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1.0)
            .animation(.spring(response: 0.25, dampingFraction: 0.7), value: configuration.isPressed)
    }
}

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService
    @State private var showGengComingSoon = false
    #if DEBUG
    @State private var showDebugLocationToggled = false
    #endif

    var body: some View {
        VStack(spacing: 24) {
            HStack {
                Spacer()
                Image(systemName: "gearshape.fill")
                    .foregroundStyle(.secondary)
                    .opacity(0.4)
                    #if DEBUG
                    .onLongPressGesture(minimumDuration: 0.6) {
                        toggleDebugLocation()
                    }
                    #endif
            }

            Image("Logo")
                .resizable()
                .scaledToFit()
                .frame(width: 190, height: 190)
                .clipShape(RoundedRectangle(cornerRadius: 36))

            Text(Copy.homeGreeting)
                .font(.makanDisplay(28))
                .foregroundStyle(Color.kicap)

            VStack(spacing: 16) {
                Button {
                    if case .authorized = locationService.state {
                        router.push(.soloPreferences)
                    } else {
                        router.push(.locationPermission)
                    }
                } label: {
                    VStack(spacing: 6) {
                        Text("👤").font(.system(size: 36))
                        Text("SOLO").font(.makanDisplay(20))
                        Text("Pick for me").font(.makanBody(14))
                    }
                    .foregroundStyle(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 24)
                    .background(Color.sambalRed)
                    .clipShape(RoundedRectangle(cornerRadius: 30))
                    .shadow(color: Color.kicap.opacity(0.12), radius: 8, y: 4)
                }
                .buttonStyle(PressableCardStyle())

                Button {
                    showGengComingSoon = true
                } label: {
                    VStack(spacing: 6) {
                        Text("👥").font(.system(size: 36))
                        Text("GENG").font(.makanDisplay(20))
                        Text("Settle for us").font(.makanBody(14))
                        Text("COMING SOON").font(.makanBody(11)).foregroundStyle(.secondary)
                    }
                    .foregroundStyle(Color.kicap.opacity(0.5))
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 24)
                    .background(Color.kicap.opacity(0.06))
                    .clipShape(RoundedRectangle(cornerRadius: 30))
                    .shadow(color: Color.kicap.opacity(0.06), radius: 6, y: 3)
                }
                .buttonStyle(PressableCardStyle())
            }
            .padding(.horizontal)

            Spacer()

            MascotView(mood: .idle, caption: Copy.homeTagline)
        }
        .padding()
        .frame(maxHeight: .infinity)
        .background(Color.nasiCream)
        .alert(Copy.gengComingSoon, isPresented: $showGengComingSoon) {
            Button("Okay", role: .cancel) {}
        }
        #if DEBUG
        .alert(
            DebugLocationOverride.isEnabled ? "Debug: fixture location ON" : "Debug: fixture location OFF",
            isPresented: $showDebugLocationToggled
        ) {
            Button("Okay", role: .cancel) {}
        } message: {
            Text(DebugLocationOverride.isEnabled
                 ? "Using Bangi test coordinates instead of device GPS."
                 : "Using real device/Simulator GPS.")
        }
        #endif
    }

    #if DEBUG
    private func toggleDebugLocation() {
        UINotificationFeedbackGenerator().notificationOccurred(.success)
        DebugLocationOverride.isEnabled.toggle()
        showDebugLocationToggled = true
        if case .authorized = locationService.state {
            locationService.requestLocation()
        }
    }
    #endif
}
