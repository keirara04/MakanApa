import SwiftUI
import UIKit
import UserNotifications

/// Shown once, after the user's first accepted pick (see ResultView.afterAccept()) — once the app
/// has proven useful, and before the OS permission dialog, not instead of it. Visual hierarchy matters here: the two bullets are
/// things the app will send once permission is granted, the release toggle is a separate
/// MakanApa preference (not itself a permission), and only the CTA actually triggers the
/// system-level prompt.
struct NotificationPrimingView: View {
    let onFinished: () -> Void

    @State private var wantsReleaseAnnouncements = false
    @State private var wantsMealtimePicks = false

    var body: some View {
        VStack(spacing: 28) {
            Spacer(minLength: 0)

            MascotView(mood: .idle, size: 120)

            VStack(spacing: 16) {
                VStack(spacing: 6) {
                    Text("Stay in the loop 🍜")
                        .font(.makanDisplay(24))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                }

                VStack(alignment: .leading, spacing: 10) {
                    Text("You'll receive:")
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                    bullet("Restaurant submission updates")
                    bullet("Important account notices")
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(.horizontal, 32)

                VStack(alignment: .leading, spacing: 8) {
                    Text("Optional")
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                    Toggle("Mealtime picks · max 1 a day", isOn: $wantsMealtimePicks)
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap)
                        .tint(.sambalRed)
                    Toggle("MakanApa news & new releases", isOn: $wantsReleaseAnnouncements)
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap)
                        .tint(.sambalRed)
                }
                .padding(.horizontal, 32)
                .padding(.top, 4)
            }

            Spacer(minLength: 0)

            VStack(spacing: 14) {
                MakanPrimaryButton(title: "Turn On Notifications", action: requestPermission)
                    .padding(.horizontal, 32)

                Button("Maybe Later", action: skip)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .frame(minHeight: 44)
            }
            .padding(.bottom, 16)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream.ignoresSafeArea())
    }

    private func bullet(_ text: String) -> some View {
        Label(text, systemImage: "checkmark")
            .font(.makanBody(14))
            .foregroundStyle(Color.kicap)
    }

    private func requestPermission() {
        Task {
            await persistReleasePreferenceIfAuthenticated()

            let granted = (try? await UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge])) ?? false
            if granted {
                await UIApplication.shared.registerForRemoteNotifications()
            }
            onFinished()
        }
    }

    private func skip() {
        onFinished()
    }

    /// Only reachable signed in now, but kept defensive: with no session the choice is simply
    /// dropped — the backend defaults release_announcements to false, matching this screen.
    private func persistReleasePreferenceIfAuthenticated() async {
        guard case .authenticated = AuthStore.shared.session, wantsReleaseAnnouncements || wantsMealtimePicks else { return }
        _ = try? await APIClient.updateNotificationPreferences(
            UpdateNotificationPreferencesRequestBody(
                releaseAnnouncements: wantsReleaseAnnouncements ? true : nil,
                mealtimeNudges: wantsMealtimePicks ? true : nil
            )
        )
    }
}

#Preview {
    NotificationPrimingView(onFinished: {})
}
