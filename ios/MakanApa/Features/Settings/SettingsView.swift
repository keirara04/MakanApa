import SwiftUI
import UIKit

struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(LocationService.self) private var locationService
    private var authStore = AuthStore.shared
    @State private var showingAboutInfo = false
    @State private var showingDeleteAccount = false
    @State private var notificationPreferences: NotificationPreferences?

    var body: some View {
        NavigationStack {
            List {
                if case .authenticated(let user) = authStore.session {
                    Section("Account") {
                        NavigationLink {
                            EditProfileView()
                        } label: {
                            HStack {
                                Text("Profile")
                                    .foregroundStyle(Color.kicap)
                                Spacer()
                                Text(user.email)
                                    .foregroundStyle(.secondary)
                            }
                        }
                    }

                    if let preferences = notificationPreferences {
                        Section("Notifications") {
                            Toggle("Submission updates", isOn: Binding(
                                get: { preferences.communitySubmissions },
                                set: { newValue in
                                    notificationPreferences?.communitySubmissions = newValue
                                    updateNotificationPreferences(UpdateNotificationPreferencesRequestBody(communitySubmissions: newValue))
                                }
                            ))
                            Toggle("Account notices", isOn: Binding(
                                get: { preferences.accountAdmin },
                                set: { newValue in
                                    notificationPreferences?.accountAdmin = newValue
                                    updateNotificationPreferences(UpdateNotificationPreferencesRequestBody(accountAdmin: newValue))
                                }
                            ))
                            Toggle("News & new releases", isOn: Binding(
                                get: { preferences.releaseAnnouncements },
                                set: { newValue in
                                    notificationPreferences?.releaseAnnouncements = newValue
                                    updateNotificationPreferences(UpdateNotificationPreferencesRequestBody(releaseAnnouncements: newValue))
                                }
                            ))
                        }
                        .tint(.sambalRed)
                    }

                    if user.isSuperadmin {
                        Section("Admin") {
                            NavigationLink {
                                AdminUsersView()
                            } label: {
                                HStack {
                                    Image(systemName: "person.2.fill")
                                        .foregroundStyle(Color.sambalRed)
                                    Text("Beta Users")
                                        .foregroundStyle(Color.kicap)
                                }
                            }
                            NavigationLink {
                                CommunityPlacesView()
                            } label: {
                                HStack {
                                    Image(systemName: "mappin.and.ellipse")
                                        .foregroundStyle(Color.sambalRed)
                                    Text("Community Places")
                                        .foregroundStyle(Color.kicap)
                                }
                            }
                            NavigationLink {
                                CommunityRequestsView()
                            } label: {
                                HStack {
                                    Image(systemName: "text.bubble.fill")
                                        .foregroundStyle(Color.sambalRed)
                                    Text("Community Requests")
                                        .foregroundStyle(Color.kicap)
                                }
                            }
                        }
                    }
                }

                Section {
                    NavigationLink {
                        FavoritesView()
                    } label: {
                        HStack {
                            Image(systemName: "heart.fill")
                                .foregroundStyle(Color.sambalRed)
                            Text("Saved")
                                .foregroundStyle(Color.kicap)
                        }
                    }
                }

                Section {
                    Picker("Open \"Jom Makan\" in", selection: mapProviderBinding) {
                        ForEach(MapProvider.allCases) { provider in
                            Text(provider.label).tag(provider)
                        }
                    }
                    .tint(Color.kicap)
                } header: {
                    Text("Maps")
                } footer: {
                    Text("Used when you tap Jom Makan or Directions to head to a restaurant.")
                }

                Section("Location") {
                    Button {
                        openSystemSettings()
                    } label: {
                        HStack {
                            Text("Location Access")
                                .foregroundStyle(Color.kicap)
                            Spacer()
                            Text(locationStatusLabel)
                                .foregroundStyle(.secondary)
                            Image(systemName: "chevron.right")
                                .font(.system(size: 12, weight: .semibold))
                                .foregroundStyle(.secondary)
                        }
                    }
                }

                #if DEBUG
                Section {
                    Toggle(isOn: debugLocationBinding) {
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Use test location")
                                .foregroundStyle(Color.kicap)
                            Text("Bangi, Selangor")
                                .font(.makanBody(12))
                                .foregroundStyle(.secondary)
                        }
                    }
                    .tint(.sambalRed)
                } header: {
                    HStack(spacing: 6) {
                        Text("Development")
                        Text("DEBUG")
                            .font(.makanBody(9))
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Color.kunyit.opacity(0.3))
                            .clipShape(Capsule())
                    }
                }
                #endif

                Section("About") {
                    Button {
                        showingAboutInfo = true
                    } label: {
                        HStack {
                            Text("MakanApa?")
                                .foregroundStyle(Color.kicap)
                            Spacer()
                            Image(systemName: "chevron.right")
                                .font(.system(size: 12, weight: .semibold))
                                .foregroundStyle(.secondary)
                        }
                    }
                    HStack {
                        Text("Version")
                            .foregroundStyle(Color.kicap)
                        Spacer()
                        Text(appVersionLabel)
                            .foregroundStyle(.secondary)
                    }
                    if let url = URL(string: Copy.privacyPolicyURL) {
                        Link(destination: url) {
                            HStack {
                                Text("Privacy Policy")
                                    .foregroundStyle(Color.kicap)
                                Spacer()
                                Image(systemName: "chevron.right")
                                    .font(.system(size: 12, weight: .semibold))
                                    .foregroundStyle(.secondary)
                            }
                        }
                    }
                }

                Section {
                    Button(role: .destructive) {
                        Task { await authStore.logout() }
                    } label: {
                        Text("Log out")
                    }
                }

                Section {
                    Button(role: .destructive) {
                        showingDeleteAccount = true
                    } label: {
                        Text("Delete account")
                    }
                }

                Section {
                    VStack(spacing: 6) {
                        MascotView(mood: .idle, size: 60)
                        Text(Copy.tagline)
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 8)
                    .listRowBackground(Color.clear)
                }
            }
            .navigationTitle("Settings")
            .navigationBarTitleDisplayMode(.inline)
            .alert("MakanApa?", isPresented: $showingAboutInfo) {
            } message: {
                Text(Copy.aboutDescription)
            }
            .sheet(isPresented: $showingDeleteAccount) {
                DeleteAccountSheet()
            }
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Done") { dismiss() }
                }
            }
            .task {
                guard case .authenticated = authStore.session else { return }
                notificationPreferences = try? await APIClient.fetchNotificationPreferences().preferences
            }
        }
    }

    /// Fire-and-forget PATCH — the toggle's own binding already applied the optimistic local
    /// update, matching the map provider Picker's pattern above of never blocking on the network.
    private func updateNotificationPreferences(_ body: UpdateNotificationPreferencesRequestBody) {
        Task {
            _ = try? await APIClient.updateNotificationPreferences(body)
        }
    }

    private var locationStatusLabel: String {
        switch locationService.state {
        case .authorized: return "While Using"
        case .denied: return "Not Allowed"
        case .notDetermined: return "Not Asked Yet"
        case .unavailable: return "Unavailable"
        }
    }

    private var appVersionLabel: String {
        let version = Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "—"
        let build = Bundle.main.object(forInfoDictionaryKey: "CFBundleVersion") as? String ?? "—"
        return "\(version) (\(build))"
    }

    private var mapProviderBinding: Binding<MapProvider> {
        Binding(
            get: { MapProviderPreference.current },
            set: { MapProviderPreference.current = $0 }
        )
    }

    private func openSystemSettings() {
        guard let url = URL(string: UIApplication.openSettingsURLString) else { return }
        UIApplication.shared.open(url)
    }

    #if DEBUG
    private var debugLocationBinding: Binding<Bool> {
        Binding(
            get: { DebugLocationOverride.isEnabled },
            set: { newValue in
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                DebugLocationOverride.isEnabled = newValue
                if case .authorized = locationService.state {
                    locationService.requestLocation()
                }
            }
        )
    }
    #endif
}
