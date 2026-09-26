import SwiftUI
import UIKit

/// Settings, grouped by job: profile header, then Your MakanApa (your stuff) · Preferences (how
/// the app behaves) · Privacy & safety · Help, then account actions. Less common screens
/// (notification toggles, legal pages, admin tools) live one tap deeper.
struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    private var authStore = AuthStore.shared

    @State private var showingAboutInfo = false
    @State private var showingDeleteAccount = false
    @State private var confirmingLogout = false
    @State private var showingSignIn = false
    @State private var appeared = false
    @State private var halalOnly = HalalPreference.isOn
    @State private var mapProvider = MapProviderPreference.current
    /// Prefetched so the Notifications screen opens already filled — loading it on push made it
    /// swap spinner → content mid-transition (a visible flicker no other page had).
    @State private var notificationPreferences: NotificationPreferences?
    /// What this account has added for others — shown under the name as credit for contributing.
    @State private var contributions: MyContributionsResponse?

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 20) {
                    if case .authenticated(let user) = authStore.session {
                        Group {
                            if user.isGuestAccount {
                                guestHeader
                            } else {
                                profileHeader(user)
                            }
                        }
                        .entrance(0, appeared: appeared, reduceMotion: reduceMotion)
                        yourMakanApaCard
                            .entrance(1, appeared: appeared, reduceMotion: reduceMotion)
                    }

                    preferencesCard
                        .entrance(2, appeared: appeared, reduceMotion: reduceMotion)

                    privacyCard
                        .entrance(3, appeared: appeared, reduceMotion: reduceMotion)

                    if case .authenticated(let user) = authStore.session, user.isSuperadmin {
                        adminCard
                            .entrance(3, appeared: appeared, reduceMotion: reduceMotion)
                    }

                    helpCard
                        .entrance(4, appeared: appeared, reduceMotion: reduceMotion)

                    #if DEBUG
                    debugCard
                        .entrance(4, appeared: appeared, reduceMotion: reduceMotion)
                    #endif

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
            .accountSignInSheet(isPresented: $showingSignIn, source: "settings")
            .onAppear {
                guard !appeared else { return }
                appeared = true
            }
            .task {
                guard case .authenticated = authStore.session, notificationPreferences == nil else { return }
                notificationPreferences = try? await APIClient.fetchNotificationPreferences().preferences
            }
            .task(id: authStore.session.isGuest) {
                // A guest has nothing to credit yet; reloads right after a guest signs up here.
                guard case .authenticated(let user) = authStore.session, !user.isGuestAccount else { return }
                contributions = try? await APIClient.myContributions()
            }
        }
    }

    // MARK: - Profile

    /// "3 places · 2 halal checks · 5 photos" — only what's non-zero, nil when there's nothing.
    private var contributionsLine: String? {
        guard let contributions else { return nil }
        let parts = [
            (contributions.placesAdded, "place", "places"),
            (contributions.halalVerified, "halal check", "halal checks"),
            (contributions.photosAdded, "photo", "photos"),
            (contributions.posts, "post", "posts"),
        ]
        .filter { $0.0 > 0 }
        .map { "\($0.0) \($0.0 == 1 ? $0.1 : $0.2)" }
        return parts.isEmpty ? nil : parts.joined(separator: " · ")
    }

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
                    if let email = user.email {
                        Text(email)
                            .font(.makanBody(13))
                            .foregroundStyle(.secondary)
                            .lineLimit(1)
                    }
                    if let community = user.university ?? user.area {
                        Label(community, systemImage: user.university != nil ? "graduationcap.fill" : "mappin")
                            .font(.makanBody(12))
                            .foregroundStyle(Color.sambalRed)
                            .padding(.horizontal, 8)
                            .padding(.vertical, 3)
                            .background(Color.sambalRed.opacity(0.1), in: Capsule())
                            .padding(.top, 2)
                    }
                    if contributions?.trustedContributor == true {
                        Label("Trusted contributor", systemImage: "checkmark.seal.fill")
                            .font(.makanBody(12))
                            .foregroundStyle(Color.pandan)
                            .padding(.horizontal, 8)
                            .padding(.vertical, 3)
                            .background(Color.pandan.opacity(0.12), in: Capsule())
                            .padding(.top, 2)
                    }
                    if let line = contributionsLine {
                        Text(line)
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                            .lineLimit(2)
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

    /// A guest has no profile to edit — this is where they find sign-in (App Review wants the
    /// app usable without an account, not sign-up hidden).
    private var guestHeader: some View {
        Button {
            showingSignIn = true
        } label: {
            HStack(spacing: 14) {
                MascotView(mood: .idle, size: 56)
                    .frame(width: 64, height: 64)
                    .background(Color.kunyit.opacity(0.25))
                    .clipShape(Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 4) {
                    Text("Using MakanApa as a guest")
                        .font(.makanDisplay(18))
                        .foregroundStyle(Color.kicap)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                    Text(Copy.guestSettingsDetail)
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                        .fixedSize(horizontal: false, vertical: true)
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
        .accessibilityHint("Sign in or create an account")
    }

    // MARK: - Your MakanApa

    /// Your own stuff — what the app learned, what you saved, what you added.
    private var yourMakanApaCard: some View {
        SettingsCard(title: "Your MakanApa") {
            SettingsNavRow(icon: "sparkles", tint: .sambalRed, title: "Your Selera", subtitle: "What I've learned about your taste") {
                SeleraView()
            }
            SettingsDivider()
            SettingsNavRow(icon: "heart.fill", tint: .sambalRed, title: "Saved places", subtitle: "Places you kept") {
                FavoritesView()
            }
            SettingsDivider()
            SettingsNavRow(icon: "mappin.and.ellipse", tint: .pandan, title: "My places", subtitle: "Places you've added or edited") {
                MySubmissionsView()
            }
        }
    }

    // MARK: - Preferences

    private var preferencesCard: some View {
        SettingsCard(title: "Preferences") {
            // First: without it Nearby and every pick are guessing, so a problem here should be
            // the first thing seen, with the fix right on the row.
            locationRow

            SettingsDivider()

            SettingsRow(icon: "checkmark.seal.fill", tint: .pandan, title: "Hide non-halal", subtitle: "Hides places known to be non-halal") {
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

            if case .authenticated = authStore.session {
                SettingsDivider()
                SettingsNavRow(icon: "bell.badge.fill", tint: .kunyit, title: "Notifications", subtitle: "Choose what pings you") {
                    NotificationSettingsView(preferences: $notificationPreferences)
                }
            }
        }
    }

    // MARK: - Location

    /// Says what location is doing for you and carries its own fix: "Allow" asks in-app the
    /// first time, "Turn on" / "Manage" go to iOS Settings (the only place a denial or
    /// Approximate Location can be changed), "Try again" re-requests a fix.
    private var locationRow: some View {
        let status = locationStatus
        return SettingsRow(icon: status.icon, tint: status.tint, title: status.title, subtitle: status.subtitle) {
            switch status {
            case .notAsked:
                LocationActionButton(title: "Allow", isProminent: true) {
                    locationService.requestLocation()
                }
            case .off:
                LocationActionButton(title: "Turn on", isProminent: true, action: openSystemSettings)
                    .accessibilityHint("Opens iOS Settings")
            case .unavailable:
                LocationActionButton(title: "Try again", isProminent: false) {
                    locationService.requestLocation()
                }
            case .on, .approximate, .locating:
                Button(action: openSystemSettings) {
                    HStack(spacing: 3) {
                        Text("Manage")
                        Image(systemName: "arrow.up.right").font(.caption2.weight(.semibold))
                    }
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap.opacity(0.6))
                    .frame(minHeight: 44)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityHint("Opens iOS Settings")
            }
        }
        .animation(reduceMotion ? nil : Motion.standard, value: status)
    }

    private var locationStatus: LocationStatus {
        switch locationService.state {
        case .authorized: return locationService.isPrecise ? .on : .approximate
        case .denied: return .off
        case .unavailable: return .unavailable
        case .notDetermined:
            // Also what an allowed app reports while its first fix is on the way.
            switch locationService.authorization {
            case .authorizedWhenInUse, .authorizedAlways: return .locating
            case .denied, .restricted: return .off
            default: return .notAsked
            }
        }
    }

    // MARK: - Privacy & safety

    private var privacyCard: some View {
        SettingsCard(title: "Privacy & safety") {
            if case .authenticated = authStore.session {
                SettingsNavRow(icon: "hand.raised.fill", tint: .pandan, title: "Blocked people", subtitle: "People you've hidden") {
                    BlockedUsersView()
                }
                SettingsDivider()
            }
            SettingsNavRow(icon: "lock.fill", tint: .kicap, title: "Privacy & legal", subtitle: "Privacy Policy, Terms, Community Guidelines") {
                LegalView()
            }
        }
    }

    private var adminCard: some View {
        SettingsCard(title: "Admin") {
            SettingsNavRow(icon: "wrench.and.screwdriver.fill", tint: .kicap, title: "Admin tools", subtitle: "Users, places, halal & requests") {
                AdminToolsView()
            }
        }
    }

    // MARK: - Help

    private var helpCard: some View {
        SettingsCard(title: "Help") {
            Button { showingAboutInfo = true } label: {
                SettingsRow(icon: "questionmark.circle.fill", tint: .kunyit, title: "What is MakanApa?", subtitle: nil) { Chevron() }
            }
            .buttonStyle(.plain)

            if let url = URL(string: Copy.supportURL) {
                SettingsDivider()
                ExternalLinkRow(url: url, icon: "lifepreserver.fill", tint: .sambalRed, title: "Help & support")
            }

            if let url = URL(string: "mailto:\(Copy.supportEmail)?subject=MakanApa%20support") {
                SettingsDivider()
                ExternalLinkRow(url: url, icon: "envelope.fill", tint: .pandan, title: "Contact & report a problem", subtitle: Copy.supportEmail)
            }
        }
    }

    // MARK: - Account actions

    @ViewBuilder
    private var accountActions: some View {
        if case .authenticated(let user) = authStore.session {
            VStack(spacing: 10) {
                // Logging a guest out would just strand their history — sign-in lives in the
                // header instead. "Delete account" stays: guests can still wipe their data.
                if !user.isGuestAccount {
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
                }

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
                    SettingsCard(title: "Makan") {
                        toggle("Mealtime picks", "A pick near you at lunch or dinner · max 1 a day", icon: "fork.knife", tint: .sambalRed,
                               value: preferences.mealtimeNudges) { $0.mealtimeNudges = $1 } body: { UpdateNotificationPreferencesRequestBody(mealtimeNudges: $0) }
                    }
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

// MARK: - Privacy & legal screen

/// Privacy Policy, Terms and Community Guidelines — reference pages people rarely open, so they
/// sit one tap below Settings instead of taking three rows of it.
struct LegalView: View {
    private var links: [(title: String, icon: String, tint: Color, url: URL)] {
        [
            ("Privacy Policy", "lock.fill", Color.kicap, Copy.privacyPolicyURL),
            ("Terms of Use", "doc.text.fill", Color.kicap, Copy.termsURL),
            ("Community Guidelines", "person.2.fill", Color.pandan, Copy.communityGuidelinesURL),
        ]
        .compactMap { title, icon, tint, string in URL(string: string).map { (title, icon, tint, $0) } }
    }

    var body: some View {
        ScrollView {
            SettingsCard(title: "Legal") {
                ForEach(Array(links.enumerated()), id: \.element.title) { index, link in
                    if index > 0 { SettingsDivider() }
                    ExternalLinkRow(url: link.url, icon: link.icon, tint: link.tint, title: link.title)
                }
            }
            .padding(16)
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .navigationTitle("Privacy & legal")
        .navigationBarTitleDisplayMode(.inline)
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

/// What the Location row shows — one case per thing the user can actually do about it.
private enum LocationStatus: Equatable {
    case on, approximate, locating, notAsked, off, unavailable

    var icon: String {
        switch self {
        case .on, .approximate, .locating: "location.fill"
        case .notAsked: "location"
        case .off: "location.slash.fill"
        case .unavailable: "location.slash"
        }
    }

    var tint: Color {
        switch self {
        case .on, .locating: .pandan
        case .approximate, .notAsked, .unavailable: .kunyit
        case .off: .sambalRed
        }
    }

    var title: String {
        self == .off ? "Location is off" : "Location"
    }

    var subtitle: String {
        switch self {
        case .on: "On while you use MakanApa"
        case .approximate: "Approximate only. Precise finds closer spots."
        case .locating: "On. Finding you…"
        case .notAsked: "So MakanApa can find makan near you"
        case .off: "Nearby and picks can't see what's around you"
        case .unavailable: "Couldn't get your location just now"
        }
    }
}

/// Compact capsule for the Location row's fix — filled when the row is blocking something.
private struct LocationActionButton: View {
    let title: String
    let isProminent: Bool
    let action: () -> Void

    var body: some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            action()
        } label: {
            Text(title)
                .font(.makanBody(13).weight(.semibold))
                .foregroundStyle(isProminent ? Color.white : Color.sambalRed)
                .padding(.horizontal, 14)
                .frame(minHeight: 32)
                .background(isProminent ? Color.sambalRed : Color.sambalRed.opacity(0.1), in: Capsule())
                .frame(minHeight: 44)
                .contentShape(Rectangle())
        }
        .buttonStyle(PressCompressStyle())
    }
}

/// A row that pushes another Settings screen.
private struct SettingsNavRow<Destination: View>: View {
    let icon: String
    let tint: Color
    let title: String
    let subtitle: String?
    @ViewBuilder var destination: () -> Destination

    var body: some View {
        NavigationLink(destination: destination) {
            SettingsRow(icon: icon, tint: tint, title: title, subtitle: subtitle) { Chevron() }
        }
        .buttonStyle(.plain)
    }
}

/// A row that leaves the app (web page or mail) — the trailing arrow says so.
private struct ExternalLinkRow: View {
    let url: URL
    let icon: String
    let tint: Color
    let title: String
    var subtitle: String? = nil

    var body: some View {
        Link(destination: url) {
            SettingsRow(icon: icon, tint: tint, title: title, subtitle: subtitle) {
                Image(systemName: "arrow.up.right").font(.caption.weight(.semibold)).foregroundStyle(.tertiary)
            }
        }
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
