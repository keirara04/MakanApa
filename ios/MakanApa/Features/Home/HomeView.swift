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
    @State private var showSettings = false

    var body: some View {
        VStack(spacing: 24) {
            HStack {
                (Text("Makan").foregroundStyle(Color.kicap) + Text("Apa?").foregroundStyle(Color.sambalRed))
                    .font(.makanDisplay(20))

                Spacer()

                Button {
                    showSettings = true
                } label: {
                    Image(systemName: "gearshape.fill")
                        .foregroundStyle(.secondary)
                        .font(.system(size: 18))
                        .frame(width: 44, height: 44)
                        .contentShape(Rectangle())
                }
                .accessibilityLabel("Settings")
            }

            VStack(spacing: 6) {
                Text(Copy.homeGreeting)
                    .font(.makanDisplay(28))
                    .foregroundStyle(Color.kicap)
                Text(Copy.homeSubtext)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)
            }
            .padding(.top, 12)

            VStack(spacing: 16) {
                Button {
                    if case .authorized = locationService.state {
                        router.push(.soloPreferences)
                    } else {
                        router.push(.locationPermission)
                    }
                } label: {
                    HStack(spacing: 16) {
                        Image("SoloIllustration")
                            .resizable()
                            .scaledToFit()
                            .frame(width: 52, height: 52)
                            .accessibilityHidden(true)

                        VStack(alignment: .leading, spacing: 2) {
                            Text("SOLO").font(.makanDisplay(20))
                            Text("Pick for me").font(.makanBody(14))
                        }
                        .foregroundStyle(.white)

                        Spacer()

                        Image(systemName: "chevron.right")
                            .foregroundStyle(.white.opacity(0.8))
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 22)
                    .background(Color.sambalRed)
                    .clipShape(RoundedRectangle(cornerRadius: 30))
                    .shadow(color: Color.kicap.opacity(0.12), radius: 8, y: 4)
                }
                .buttonStyle(PressableCardStyle())

                Button {
                    showGengComingSoon = true
                } label: {
                    HStack(spacing: 16) {
                        Image("GengIllustration")
                            .resizable()
                            .scaledToFit()
                            .frame(width: 52, height: 52)
                            .accessibilityHidden(true)

                        VStack(alignment: .leading, spacing: 2) {
                            Text("GENG").font(.makanDisplay(20))
                            Text("Settle for us").font(.makanBody(14))
                        }
                        .foregroundStyle(Color.kicap.opacity(0.65))

                        Spacer()

                        Text("Soon")
                            .font(.makanBody(10))
                            .foregroundStyle(Color.kicap.opacity(0.5))
                            .padding(.horizontal, 8)
                            .padding(.vertical, 4)
                            .background(Color.kicap.opacity(0.08))
                            .clipShape(Capsule())

                        Image(systemName: "chevron.right")
                            .foregroundStyle(Color.kicap.opacity(0.3))
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 22)
                    .background(Color.kicap.opacity(0.08))
                    .clipShape(RoundedRectangle(cornerRadius: 30))
                    .shadow(color: Color.kicap.opacity(0.06), radius: 6, y: 3)
                }
                .buttonStyle(PressableCardStyle())
            }
            .padding(.horizontal)

            Spacer()

            MascotView(mood: .idle, size: 180)
        }
        .padding()
        .frame(maxHeight: .infinity)
        .background(Color.nasiCream)
        .alert(Copy.gengComingSoon, isPresented: $showGengComingSoon) {
            Button("Okay", role: .cancel) {}
        }
        .sheet(isPresented: $showSettings) {
            SettingsView()
        }
    }
}
