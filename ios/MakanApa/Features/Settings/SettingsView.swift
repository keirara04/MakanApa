import SwiftUI
import UIKit

struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(LocationService.self) private var locationService
    private var authStore = AuthStore.shared
    @State private var showingAboutInfo = false

    var body: some View {
        NavigationStack {
            List {
                if case .authenticated(let user) = authStore.session {
                    Section("Account") {
                        HStack {
                            Text("Email")
                                .foregroundStyle(Color.kicap)
                            Spacer()
                            Text(user.email)
                                .foregroundStyle(.secondary)
                        }
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
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Done") { dismiss() }
                }
            }
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
