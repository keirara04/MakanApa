import SwiftUI
import UIKit

/// Settings, reorganised around what people actually come here for. The old single List had
/// twelve sections stacked on top of each other; now it's a profile header, four shortcut tiles,
/// three grouped cards (Preferences · Community · About), then account actions — and the less
/// common screens (notification toggles, admin tools) live one tap deeper.
struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    private var authStore = AuthStore.shared

    @State private var showingAboutInfo = false
    @State private var showingDeleteAccount = false
    @State private var confirmingLogout = false
    @State private var appeared = false
    @State private var halalOnly = HalalPreference.isOn
    @State private var mapProvider = MapProviderPreference.current
    /// Prefetched so the Notifications screen opens already filled — loading it on push made it
    /// swap spinner → content mid-transition (a visible flicker no other page had).
    @State private var notificationPreferences: NotificationPreferences?

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 20) {
                    if case .authenticated(let user) = authStore.session {
                        profileHeader(user)
                            .entrance(0, appeared: appeared, reduceMotion: reduceMotion)
                        shortcutTiles
                            .entrance(1, appeared: appeared, reduceMotion: reduceMotion)
                    }

                    preferencesCard
                        .entrance(2, appeared: appeared, reduceMotion: reduceMotion)

                    if case .authenticated(let user) = authStore.session {
                        communityCard(isAdmin: user.isSuperadmin)
                            .entrance(3, appeared: appeared, reduceMotion: reduceMotion)
                    }

                    #if DEBUG
                    debugCard
                        .entrance(4, appeared: appeared, reduceMotion: reduceMotion)
                    #endif

                    aboutCard
                        .entrance(4, appeared: appeared, reduceMotion: reduceMotion)

                    accountActions
                        .entrance(5, appeared: appeared, reduceMotion: reduceMotion)

                    footer
                        .entrance(5, appeared: appeared, reduceMotion: reduceMotion)
                }
                .padding(.horizontal, 16)
                .padding(.top, 8)
                .padding(.bottom, 32)
            }
            .scrollIndicators(.hidden)
            .background(Color.nasiCream.ignoresSafeArea())
            .navigationTitle("Settings")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Done") { dismiss() }
                        .fontWeight(.semibold)
                }
            }
            .alert("MakanApa?", isPresented: $showingAboutInfo) {
            } message: {
                Text(Copy.aboutDescription)
            }
            .confirmationDialog("Log out of MakanApa?", isPresented: $confirmingLogout, titleVisibility: .visible) {
                Button("Log out", role: .destructive) {
                    Task { await authStore.logout() }
                }
            }
            .sheet(isPresented: $showingDeleteAccount) {
                DeleteAccountSheet()
            }
            .onAppear {
                guard !appeared else { return }
                appeared = true
            }
            .task {
                guard case .authenticated = authStore.session, notificationPreferences == nil else { return }
                notificationPreferences = try? await APIClient.fetchNotificationPreferences().preferences
            }
        }
    }

    // MARK: - Profile

    private func profileHeader(_ user: AuthUser) -> some View {
        NavigationLink {
            EditProfileView()
        } label: {
            HStack(spacing: 14) {
                Image(AvatarCharacter(key: user.avatarKey).imageName)
                    .resizable()
                    .scaledToFill()
                    .frame(width: 64, height: 64)
                    .background(Color.kunyit.opacity(0.25))
                    .clipShape(Circle())
                    .overlay(Circle().stroke(.white, lineWidth: 3))
                    .shadow(color: Color.kicap.opacity(0.1), radius: 6, y: 3)
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 4) {
                    Text(user.name?.isEmpty == false ? user.name! : "Your profile")
                        .font(.makanDisplay(20))
                        .foregroundStyle(Color.kicap)
                        .lineLimit(1)
                    Text(user.email)
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                    if let community = user.university ?? user.area {
                        Label(community, systemImage: user.university != nil ? "graduationcap.fill" : "mappin")
                            .font(.makanBody(12))
                            .foregroundStyle(Color.sambalRed)
                            .padding(.horizontal, 8)
                            .padding(.vertical, 3)
                            .background(Color.sambalRed.opacity(0.1), in: Capsule())
                            .padding(.top, 2)
                    }
                }
                Spacer(minLength: 0)
                Image(systemName: "chevron.right")
                    .font(.system(size: 13, weight: .semibold))
                    .foregroundStyle(.tertiary)
            }
            .padding(16)
            .background(Color.white, in: RoundedRectangle(cornerRadius: 24))
            .shadow(color: Color.kicap.opacity(0.05), radius: 10, y: 4)
        }
        .buttonStyle(PressCompressStyle())
        .accessibilityHint("Edit your profile")
    }

    // MARK: - Shortcut tiles

    private var shortcutTiles: some View {
        LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
            SettingsTile(icon: "sparkles", tint: .sambalRed, title: "Your Selera", subtitle: "What I've learned") {
                SeleraView()
            }
            SettingsTile(icon: "heart.fill", tint: .sambalRed, title: "Saved", subtitle: "Places you kept") {
                FavoritesView()
            }
            SettingsTile(icon: "bell.badge.fill", tint: .kunyit, title: "Notifications", subtitle: "Choose what pings you") {
                NotificationSettingsView(preferences: $notificationPreferences)
            }
            SettingsTile(icon: "hand.raised.fill", tint: .pandan, title: "Blocked", subtitle: "People you've hidden") {
                BlockedUsersView()
            }
        }
    }

    // MARK: - Preferences

    private var preferencesCard: some View {
        SettingsCard(title: "Preferences") {
            SettingsRow(icon: "checkmark.seal.fill", tint: .pandan, title: "Halal only", subtitle: "Hide places confirmed non-halal") {
                Toggle("", isOn: $halalOnly)
                    .labelsHidden()
                    .tint(.sambalRed)
            }
            .onChange(of: halalOnly) { _, isOn in
                HalalPreference.isOn = isOn
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                if case .authenticated = authStore.session {
                    Task { _ = try? await APIClient.updateHalalPreference(isOn) }
                }
            }

            SettingsDivider()

            SettingsRow(icon: "map.fill", tint: .kicap, title: "Open directions in", subtitle: "Used by Jom Makan & Directions") {
                Menu {
                    Picker("Maps app", selection: $mapProvider) {
                        ForEach(MapProvider.allCases) { provider in
                            Text(provider.label).tag(provider)
                        }
                    }
                } label: {
                    HStack(spacing: 4) {
                        Text(mapProvider.label)
                        Image(systemName: "chevron.up.chevron.down").font(.caption2)
                    }
                    .font(.makanBody(14))
                    .foregroundStyle(Color.kicap.opacity(0.7))
                }
            }
            .onChange(of: mapProvider) { _, provider in
                MapProviderPreference.current = provider
            }

            SettingsDivider()

            Button(action: openSystemSettings) {
                SettingsRow(icon: "location.fill", tint: .sambalRed, title: "Location access", subtitle: nil) {
                    StatusPill(text: locationStatusLabel, isGood: locationIsGood)
                }
            }
            .buttonStyle(.plain)
            .accessibilityHint("Opens iOS Settings")
        }
    }

    // MARK: - Community

    private func communityCard(isAdmin: Bool) -> some View {
        SettingsCard(title: "Community") {
            NavigationLink {
                MySubmissionsView()
            } label: {
                SettingsRow(icon: "mappin.and.ellipse", tint: .sambalRed, title: "My places", subtitle: "Places you've added or edited") {
                    Chevron()
                }
            }
            .buttonStyle(.plain)

            if isAdmin {
                SettingsDivider()
                NavigationLink {
                    AdminToolsView()
                } label: {
                    SettingsRow(icon: "wrench.and.screwdriver.fill", tint: .kicap, title: "Admin tools", subtitle: "Users, places, halal & requests") {
                        Chevron()
                    }
                }
                .buttonStyle(.plain)
            }
        }
    }

    // MARK: - About

    private var aboutCard: some View {
        SettingsCard(title: "About") {
            Button { showingAboutInfo = true } label: {
                SettingsRow(icon: "questionmark.circle.fill", tint: .kunyit, title: "What is MakanApa?", subtitle: nil) { Chevron() }
            }
            .buttonStyle(.plain)

            SettingsDivider()

            if let url = URL(string: "mailto:\(Copy.supportEmail)?subject=MakanApa%20support") {
                Link(destination: url) {
                    SettingsRow(icon: "envelope.fill", tint: .pandan, title: "Contact & report a problem", subtitle: Copy.supportEmail) {
                        Image(systemName: "arrow.up.right").font(.caption.weight(.semibold)).foregroundStyle(.tertiary)
                    }
                }
                SettingsDivider()
            }

            if let url = URL(string: Copy.privacyPolicyURL) {
                Link(destination: url) {
                    SettingsRow(icon: "lock.fill", tint: .kicap, title: "Privacy Policy", subtitle: nil) {
                        Image(systemName: "arrow.up.right").font(.caption.weight(.semibold)).foregroundStyle(.tertiary)
                    }
                }
            }
        }
    }

    // MARK: - Account actions

    @ViewBuilder
    private var accountActions: some View {
        if case .authenticated = authStore.session {
            VStack(spacing: 10) {
                Button {
                    confirmingLogout = true
                } label: {
                    Text("Log out")
                        .font(.makanBody(16).weight(.semibold))
                        .foregroundStyle(Color.sambalRed)
                        .frame(maxWidth: .infinity, minHeight: 52)
                        .background(Color.white, in: RoundedRectangle(cornerRadius: 18))
                }
                .buttonStyle(PressCompressStyle())

                Button {
                    showingDeleteAccount = true
                } label: {
                    Text("Delete account")
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                        .frame(minHeight: 44)
                }
            }
        }
    }

    private var footer: some View {
        VStack(spacing: 6) {
            MascotView(mood: .idle, size: 56)
            Text(Copy.tagline)
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
            Text("Version \(appVersionLabel)")
                .font(.makanBody(11))
                .foregroundStyle(.tertiary)
        }
        .frame(maxWidth: .infinity)
        .padding(.top, 4)
    }

    #if DEBUG
    private var debugCard: some View {
        SettingsCard(title: "Development · DEBUG") {
            SettingsRow(icon: "ladybug.fill", tint: .kunyit, title: "Use test location", subtitle: "Bangi, Selangor") {
                Toggle("", isOn: debugLocationBinding)
                    .labelsHidden()
                    .tint(.sambalRed)
            }
        }
    }

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

    // MARK: - Helpers

    private var locationStatusLabel: String {
        switch locationService.state {
        case .authorized: return "While Using"
        case .denied: return "Not Allowed"
        case .notDetermined: return "Not Asked Yet"
        case .unavailable: return "Unavailable"
        }
    }

    private var locationIsGood: Bool {
        if case .authorized = locationService.state { return true }
        return false
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
}

// MARK: - Notifications screen

/// The five notification toggles, grouped by who they're about — used to sit inline and made
/// up almost half of the old Settings list.
struct NotificationSettingsView: View {
    /// Owned by SettingsView (prefetched there), so this screen is populated on the first frame
    /// of the push and toggles stay in sync if you go back and forth.
    @Binding var preferences: NotificationPreferences?
    @State private var loadFailed = false

    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                if let preferences {
                    SettingsCard(title: "Your places") {
                        toggle("Submission updates", "When a place you added is reviewed", icon: "checkmark.bubble.fill", tint: .pandan,
                               value: preferences.communitySubmissions) { $0.communitySubmissions = $1 } body: { UpdateNotificationPreferencesRequestBody(communitySubmissions: $0) }
                    }
                    SettingsCard(title: "Community") {
                        toggle("Replies", "When someone replies to your post", icon: "bubble.left.fill", tint: .sambalRed,
                               value: preferences.communityReplies) { $0.communityReplies = $1 } body: { UpdateNotificationPreferencesRequestBody(communityReplies: $0) }
                        SettingsDivider()
                        toggle("Reactions", "When someone reacts to your post", icon: "flame.fill", tint: .kunyit,
                               value: preferences.communityReactions) { $0.communityReactions = $1 } body: { UpdateNotificationPreferencesRequestBody(communityReactions: $0) }
                    }
                    SettingsCard(title: "From MakanApa") {
                        toggle("Account notices", "Important changes to your account", icon: "person.crop.circle.badge.exclamationmark", tint: .kicap,
                               value: preferences.accountAdmin) { $0.accountAdmin = $1 } body: { UpdateNotificationPreferencesRequestBody(accountAdmin: $0) }
                        SettingsDivider()
                        toggle("News & new releases", "New features, now and then", icon: "megaphone.fill", tint: .sambalRed,
                               value: preferences.releaseAnnouncements) { $0.releaseAnnouncements = $1 } body: { UpdateNotificationPreferencesRequestBody(releaseAnnouncements: $0) }
                    }
                } else if loadFailed {
                    Text(Copy.connectionErrorDetail)
                        .font(.makanBody(14))
                        .foregroundStyle(.secondary)
                        .multilineTextAlignment(.center)
                        .padding(.top, 40)
                } else {
                    ProgressView().padding(.top, 40)
                }
            }
            .padding(16)
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .navigationTitle("Notifications")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            // Only a fallback when the prefetch hadn't finished/failed — never swaps content that's
            // already on screen.
            guard preferences == nil else { return }
            do {
                preferences = try await APIClient.fetchNotificationPreferences().preferences
            } catch {
                loadFailed = true
            }
        }
    }

    /// Optimistic: the switch flips instantly and the PATCH is fire-and-forget, same as before.
    private func toggle(
        _ title: String, _ subtitle: String, icon: String, tint: Color, value: Bool,
        apply: @escaping (inout NotificationPreferences, Bool) -> Void,
        body makeBody: @escaping (Bool) -> UpdateNotificationPreferencesRequestBody
    ) -> some View {
        SettingsRow(icon: icon, tint: tint, title: title, subtitle: subtitle) {
            Toggle("", isOn: Binding(
                get: { value },
                set: { newValue in
                    if var updated = preferences {
                        apply(&updated, newValue)
                        preferences = updated
                    }
                    Task { _ = try? await APIClient.updateNotificationPreferences(makeBody(newValue)) }
                }
            ))
            .labelsHidden()
            .tint(.sambalRed)
        }
    }
}

// MARK: - Admin tools screen

struct AdminToolsView: View {
    var body: some View {
        ScrollView {
            SettingsCard(title: "Moderation") {
                link("Beta users", "person.2.fill") { AdminUsersView() }
                SettingsDivider()
                link("Community places", "mappin.and.ellipse") { CommunityPlacesView() }
                SettingsDivider()
                link("Halal reviews", "checkmark.seal.fill") { HalalReviewQueueView() }
                SettingsDivider()
                link("Community requests", "text.bubble.fill") { CommunityRequestsView() }
            }
            .padding(16)
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .navigationTitle("Admin tools")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func link<Destination: View>(_ title: String, _ icon: String, @ViewBuilder destination: @escaping () -> Destination) -> some View {
        NavigationLink(destination: destination) {
            SettingsRow(icon: icon, tint: .sambalRed, title: title, subtitle: nil) { Chevron() }
        }
        .buttonStyle(.plain)
    }
}

// MARK: - Building blocks

/// White rounded card with a small caps label above — the one container every settings group uses.
struct SettingsCard<Content: View>: View {
    let title: String
    @ViewBuilder var content: () -> Content

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(title.uppercased())
                .font(.makanBody(11).weight(.semibold))
                .foregroundStyle(.secondary)
                .tracking(0.6)
                .padding(.leading, 4)
                .accessibilityAddTraits(.isHeader)
            VStack(spacing: 0) {
                content()
            }
            .background(Color.white, in: RoundedRectangle(cornerRadius: 20))
        }
    }
}

/// Tinted icon badge · title (+ subtitle) · trailing accessory. Min 56pt tall.
struct SettingsRow<Accessory: View>: View {
    let icon: String
    let tint: Color
    let title: String
    let subtitle: String?
    @ViewBuilder var accessory: () -> Accessory

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .font(.system(size: 15, weight: .semibold))
                .foregroundStyle(tint)
                .frame(width: 34, height: 34)
                .background(tint.opacity(0.12), in: RoundedRectangle(cornerRadius: 10))
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap)
                if let subtitle {
                    Text(subtitle)
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                        .lineLimit(2)
                }
            }
            Spacer(minLength: 8)
            accessory()
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 56)
        .contentShape(Rectangle())
    }
}

struct SettingsDivider: View {
    var body: some View {
        Divider().padding(.leading, 60)
    }
}

private struct Chevron: View {
    var body: some View {
        Image(systemName: "chevron.right")
            .font(.system(size: 13, weight: .semibold))
            .foregroundStyle(.tertiary)
    }
}

private struct StatusPill: View {
    let text: String
    let isGood: Bool

    var body: some View {
        HStack(spacing: 4) {
            Circle().fill(isGood ? Color.pandan : Color.sambalRed).frame(width: 6, height: 6)
            Text(text)
        }
        .font(.makanBody(12))
        .foregroundStyle(Color.kicap.opacity(0.75))
        .padding(.horizontal, 10)
        .padding(.vertical, 5)
        .background(Color.kicap.opacity(0.05), in: Capsule())
    }
}

/// Square-ish shortcut tile for the four most-used destinations.
private struct SettingsTile<Destination: View>: View {
    let icon: String
    let tint: Color
    let title: String
    let subtitle: String
    @ViewBuilder var destination: () -> Destination

    var body: some View {
        NavigationLink(destination: destination) {
            VStack(alignment: .leading, spacing: 10) {
                Image(systemName: icon)
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundStyle(tint)
                    .frame(width: 38, height: 38)
                    .background(tint.opacity(0.12), in: RoundedRectangle(cornerRadius: 12))
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.makanBody(15).weight(.semibold))
                        .foregroundStyle(Color.kicap)
                    Text(subtitle)
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white, in: RoundedRectangle(cornerRadius: 20))
        }
        .buttonStyle(PressCompressStyle())
    }
}

/// Staggered entrance: each block fades up a beat after the one above it. Instant (no offset,
/// no delay) under Reduce Motion.
private struct Entrance: ViewModifier {
    let index: Int
    let appeared: Bool
    let reduceMotion: Bool

    func body(content: Content) -> some View {
        content
            .opacity(appeared ? 1 : 0)
            .offset(y: appeared || reduceMotion ? 0 : 14)
            .animation(
                reduceMotion ? .easeOut(duration: 0.15) : .spring(response: 0.45, dampingFraction: 0.85).delay(Double(index) * 0.05),
                value: appeared
            )
    }
}

private extension View {
    func entrance(_ index: Int, appeared: Bool, reduceMotion: Bool) -> some View {
        modifier(Entrance(index: index, appeared: appeared, reduceMotion: reduceMotion))
    }
}
