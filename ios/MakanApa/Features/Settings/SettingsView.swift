import SwiftUI
import UIKit

struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(LocationService.self) private var locationService

    var body: some View {
        NavigationStack {
            List {
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
                    HStack {
                        Text("MakanApa?")
                            .foregroundStyle(Color.kicap)
                        Spacer()
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
