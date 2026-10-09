import CoreLocation
import SwiftUI

struct CommunityView: View {
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var viewModel = CommunityViewModel()
    @State private var selectedItem: CommunityFeedItem?
    @State private var headerAppeared = false
    @State private var pillBounce = false
    @State private var showingAddPlace = false
    @State private var showingMyPlaces = false
    @State private var showingCommunityAssignment = false
    @State private var showingGuide = false
    @State private var postStore = CommunityPostStore()
    @State private var postInteractions = CommunityPostInteractions()
    @State private var showingAllPosts = false
    @State private var pendingDeepLink = PendingDeepLink.shared

    var body: some View {
        Group {
            if viewModel.isUniversityUser || viewModel.isAreaUser {
                content
            } else {
                switch locationService.state {
                case .authorized:
                    content
                case .notDetermined:
                    locationPrompt(
                        headline: Copy.communityLocationPromptHeadline,
                        detail: Copy.communityLocationPromptDetail,
                        buttonTitle: Copy.communityLocationEnable
                    ) { locationService.requestLocation() }
                case .denied, .unavailable:
                    locationPrompt(
                        headline: Copy.communityLocationDeniedHeadline,
                        detail: Copy.communityLocationDeniedDetail,
                        buttonTitle: Copy.communityLocationOpenSettings
                    ) { openSystemSettings() }
                }
            }
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .onAppear { Task { await attemptLoad() } }
        .onChange(of: locationService.state) { _, _ in Task { await attemptLoad() } }
        .onChange(of: pendingDeepLink.communityPostId, initial: true) { _, postId in
            // Tapped a reply/reaction push — open that thread on top of the Community tab.
            guard let postId else { return }
            postInteractions.threadTarget = postStore.find(postId) ?? CommunityPost.placeholder(id: postId)
            pendingDeepLink.communityPostId = nil
        }
    }

    private var content: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: 22) {
                header

                if viewModel.isLoading && viewModel.feed == nil {
                    skeletonRows
                } else if let feed = viewModel.feed {
                    if isAffiliated, let picks = feed.ambassadorPicks, !picks.isEmpty || isAmbassadorHere {
                        AmbassadorPicksSection(picks: picks, community: communityShortName, isMine: isAmbassadorHere) { pick in
                            selectedItem = pick.feedItem
                        }
                        .headerEntrance(visible: headerAppeared, delay: 0.18, reduceMotion: reduceMotion)
                    }
                    if !feed.newInArea.isEmpty {
                        newInAreaSection(feed.newInArea)
                    }
                    if isAffiliated {
                        postsSection
                    }
                    if feed.trending.isEmpty {
                        if feed.newInArea.isEmpty {
                            emptyState
                        }
                    } else {
                        Label("Trending around you", systemImage: "chart.line.uptrend.xyaxis")
                            .font(.system(.headline, design: .rounded))
                            .foregroundStyle(Color.kicap)
                            .accessibilityAddTraits(.isHeader)
                        ForEach(Array(feed.trending.enumerated()), id: \.element.id) { index, item in
                            CommunityTrendingCard(rank: index + 1, item: item) {
                                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                                selectedItem = item
                            }
                        }
                    }
                } else if let apiError = viewModel.apiError {
                    errorState(for: apiError)
                }

                CompactActionCard(
                    systemImage: "questionmark.circle.fill",
                    title: Copy.communityGuideFooterTitle,
                    detail: Copy.communityGuideFooterDetail
                ) { showingGuide = true }
                    .padding(.top, 8)
            }
            .padding(.horizontal, 20)
            .padding(.top, 12)
            .padding(.bottom, 32)
        }
        .scrollIndicators(.hidden)
        .refreshable { await attemptLoad() }
        // An ambassador added/removed a pick from a place sheet — show it in the rail now.
        .onChange(of: AmbassadorPickStore.shared.picked) { _, _ in Task { await attemptLoad() } }
        .navigationDestination(isPresented: $showingAllPosts) {
            CommunityPostsView(store: postStore, communityName: communityShortName)
        }
        .navigationDestination(item: $postInteractions.threadTarget) { post in
            CommunityThreadView(postId: post.id, store: postStore)
        }
        .communityPostPresentations(postInteractions, store: postStore)
        .sheet(item: $selectedItem) { item in
            CommunityRestaurantDetailSheet(item: item)
                .presentationDetents([.medium, .large])
                .presentationDragIndicator(.visible)
        }
        .sheet(isPresented: $showingAddPlace) {
            AddPlaceFlow()
        }
        .sheet(isPresented: $showingMyPlaces) {
            NavigationStack {
                MySubmissionsView()
            }
        }
        .sheet(isPresented: $showingGuide) {
            FeatureGuideSheet(title: Copy.communityGuideTitle, pages: CommunityGuide.pages) { CommunityGuideArt(index: $0) }
        }
        .sheet(isPresented: $showingCommunityAssignment) {
            CommunityAssignmentSheet(currentUniversity: currentUniversity, currentArea: currentArea) {
                await attemptLoad()
            }
        }
    }

    // MARK: - Header

    /// One quiet row — which community you're in (tap to switch) and Add — then a generous,
    /// locally relevant headline. The tab bar already says "Community", so the row doesn't.
    private var header: some View {
        VStack(alignment: .leading, spacing: 20) {
            HStack(alignment: .center, spacing: 12) {
                communityPill
                Spacer(minLength: 8)
                addMenu
            }
            .headerEntrance(visible: headerAppeared, delay: 0, reduceMotion: reduceMotion)

            VStack(alignment: .leading, spacing: 6) {
                Text(headline)
                    .font(.largeTitle.weight(.bold))
                    .foregroundStyle(Color.kicap)
                    .fixedSize(horizontal: false, vertical: true)
                    .accessibilityAddTraits(.isHeader)
                    .headerEntrance(visible: headerAppeared, delay: 0.06, reduceMotion: reduceMotion)
                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(Color.kicapSecondary)
                    .fixedSize(horizontal: false, vertical: true)
                    .headerEntrance(visible: headerAppeared, delay: 0.12, reduceMotion: reduceMotion)
            }
        }
        .padding(.top, 4)
        .padding(.bottom, 6)
        .onAppear {
            headerAppeared = true
        }
    }

    /// Current community as a location-picker style switcher (icon · name · chevron), like a
    /// delivery app's "Deliver to". When the affiliation changes (university ↔ area ↔ public)
    /// the name slides out/in and the pill gives a small bounce.
    private var communityPill: some View {
        let badge = CommunityBadge(affiliationType: currentAffiliationType, university: currentUniversity, area: currentArea)
        let symbol = switch currentAffiliationType {
        case "university": "graduationcap.fill"
        case "area": "mappin.and.ellipse"
        default: "globe.asia.australia.fill"
        }

        return Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            showingCommunityAssignment = true
        } label: {
            HStack(spacing: 8) {
                Image(systemName: symbol)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)
                    .contentTransition(.symbolEffect(.replace))
                Text(badge.label)
                    .font(.headline)
                    .foregroundStyle(Color.kicap)
                    .lineLimit(1)
                    .id(badge.label)
                    .transition(reduceMotion ? .opacity : .asymmetric(
                        insertion: .push(from: .bottom).combined(with: .opacity),
                        removal: .push(from: .top).combined(with: .opacity)
                    ))
                Image(systemName: "chevron.down")
                    .font(.caption.weight(.bold))
                    .foregroundStyle(Color.kicapSecondary)
            }
            .padding(.horizontal, 14)
            .frame(minHeight: 44)
            .background(Color.surface, in: Capsule())
            .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
            .clipShape(Capsule())
            .scaleEffect(pillBounce ? 1.04 : 1)
            .contentShape(Capsule())
        }
        .buttonStyle(CommunityPressStyle())
        .animation(reduceMotion ? .easeOut(duration: 0.15) : .spring(response: 0.4, dampingFraction: 0.78), value: badge.label)
        .onChange(of: badge.label) { _, _ in
            guard !reduceMotion else { return }
            withAnimation(Motion.quick) {
                pillBounce = true
            } completion: {
                withAnimation(Motion.standard) { pillBounce = false }
            }
        }
        .accessibilityLabel("Community, \(badge.label). Change community")
    }

    private var addMenu: some View {
        Menu {
            Button {
                showingAddPlace = true
            } label: {
                Label(Copy.communityAddPlaceMenuItem, systemImage: "plus")
            }
            Button {
                showingMyPlaces = true
            } label: {
                Label(Copy.communityMyPlacesMenuItem, systemImage: "list.bullet")
            }
        } label: {
            Image(systemName: "plus")
                .font(.headline)
                .foregroundStyle(Color.kicap)
                .frame(width: 44, height: 44)
                .background(Color.surface, in: Circle())
                .overlay(Circle().strokeBorder(Color.hairline, lineWidth: 1))
                .contentShape(Circle())
        }
        .accessibilityLabel("Add a place or see your places")
    }

    /// Sourced from the session (`AuthStore`), not `viewModel.feed?.community` — a user's
    /// affiliation is known the moment they're logged in, so the badge/headline shouldn't wait
    /// on the trending feed to finish loading (or flicker away during a loading/error state).
    private var currentAffiliationType: String? {
        if case .authenticated(let user) = AuthStore.shared.session { return user.affiliationType }
        return nil
    }

    private var currentUniversity: String? {
        if case .authenticated(let user) = AuthStore.shared.session { return user.university }
        return nil
    }

    private var currentArea: String? {
        if case .authenticated(let user) = AuthStore.shared.session { return user.area }
        return nil
    }

    private var headline: String {
        if currentAffiliationType == "university", let university = currentUniversity {
            return String(format: Copy.communityHeadlineUniversityFormat, university)
        }
        if currentAffiliationType == "area", let area = currentArea {
            return String(format: Copy.communityHeadlineAreaFormat, area)
        }
        return Copy.communityHeadlinePublic
    }

    private var subtitle: String {
        if currentAffiliationType == "university", let university = currentUniversity {
            return String(format: Copy.communitySubtitleUniversityFormat, university)
        }
        if currentAffiliationType == "area", let area = currentArea {
            return String(format: Copy.communitySubtitleAreaFormat, area)
        }
        return Copy.communitySubtitlePublic
    }

    // MARK: - What KU is saying

    private var isAffiliated: Bool {
        currentAffiliationType == "university" || currentAffiliationType == "area"
    }

    /// The viewer represents the community whose board they're looking at.
    private var isAmbassadorHere: Bool {
        guard let role = AmbassadorPickStore.currentRole else { return false }
        return role.type == currentAffiliationType && role.name == communityShortName
    }

    private var communityShortName: String {
        (currentAffiliationType == "university" ? currentUniversity : currentArea) ?? "your community"
    }

    private var postsSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("What \(communityShortName) is saying")
                    .font(.system(.headline, design: .rounded))
                    .foregroundStyle(Color.kicap)
                    .accessibilityAddTraits(.isHeader)
                Spacer()
                if !postStore.posts.isEmpty {
                    Button(Copy.communityPostsSeeAll) { showingAllPosts = true }
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .frame(minHeight: 44)
                }
            }

            if postStore.isLoading && !postStore.hasLoaded {
                RoundedRectangle(cornerRadius: 18)
                    .fill(Color.kicap.opacity(0.06))
                    .frame(height: 110)
            } else if postStore.posts.isEmpty {
                CommunityPostsEmptyState(store: postStore, communityName: communityShortName) {
                    postInteractions.isComposingNew = true
                }
            } else {
                ForEach(postStore.posts.prefix(3)) { post in
                    CommunityPostRow(post: post) { tap in
                        Task { await postStore.react(tap.post, with: tap.type) }
                    }
                }
                if postStore.canPost {
                    Button {
                        postInteractions.isComposingNew = true
                    } label: {
                        Label(Copy.communityPostsShareCTA, systemImage: "square.and.pencil")
                            .font(.system(.subheadline, design: .rounded, weight: .semibold))
                            .foregroundStyle(.white)
                            .frame(maxWidth: .infinity, minHeight: 52)
                            .background(Color.sambalRed, in: RoundedRectangle(cornerRadius: 18))
                            .shadow(color: Color.sambalRed.opacity(0.16), radius: 12, y: 5)
                    }
                    .buttonStyle(CommunityPressStyle())
                }
            }
        }
        .padding(.bottom, 6)
    }

    // MARK: - New in your area

    private func newInAreaSection(_ items: [CommunityFeedItem]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("New in your area")
                .font(.system(.headline, design: .rounded))
                .foregroundStyle(Color.kicap)

            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 10) {
                    ForEach(items) { item in
                        NewInAreaCard(item: item) {
                            UIImpactFeedbackGenerator(style: .light).impactOccurred()
                            selectedItem = item
                        }
                    }
                }
                .padding(.horizontal, 2)
            }
        }
        .padding(.bottom, 4)
    }

    // MARK: - States

    private var skeletonRows: some View {
        VStack(spacing: 12) {
            ForEach(0..<5, id: \.self) { _ in
                RoundedRectangle(cornerRadius: 18)
                    .fill(Color.kicap.opacity(0.06))
                    .frame(height: 84)
            }
        }
        .redacted(reason: .placeholder)
        .transition(.opacity)
    }

    /// Nothing trending yet: a thin row (same shape as the guide card) instead of a big card.
    private var emptyState: some View {
        CompactActionCard(
            systemImage: "plus.circle.fill",
            title: Copy.communityEmptyAddTitle,
            detail: Copy.communityEmptyAddDetail
        ) { showingAddPlace = true }
        .transition(.opacity)
    }

    private func errorState(for error: APIError) -> some View {
        VStack(spacing: 12) {
            Text(bannerMessage(for: error))
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            Button("Retry") { Task { await attemptLoad() } }
                .font(.makanBody(14))
        }
        .frame(maxWidth: .infinity)
        .padding(.top, 40)
    }

    private func bannerMessage(for error: APIError) -> String {
        error.userFacingCopy.detail
    }

    private func locationPrompt(headline: String, detail: String, buttonTitle: String, action: @escaping () -> Void) -> some View {
        VStack(spacing: 16) {
            Spacer()
            Image(systemName: "location.circle")
                .font(.system(size: 44))
                .foregroundStyle(Color.kunyit)
            Text(headline)
                .font(.makanDisplay(18))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
            Text(detail)
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            Button(action: action) {
                Text(buttonTitle)
                    .font(.makanBody(14))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 24)
                    .padding(.vertical, 12)
                    .background(Color.sambalRed)
                    .clipShape(Capsule())
            }
            Spacer()
        }
        .padding(32)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Loading

    @MainActor
    private func attemptLoad() async {
        if viewModel.isUniversityUser || viewModel.isAreaUser {
            async let posts: Void = postStore.load()
            await viewModel.load(coordinate: currentCoordinate)
            await posts
        } else if case .authorized(let coordinate) = locationService.state {
            await viewModel.load(coordinate: coordinate)
        }
    }

    private var currentCoordinate: CLLocationCoordinate2D? {
        if case .authorized(let coordinate) = locationService.state {
            return coordinate
        }
        return nil
    }

    private func openSystemSettings() {
        guard let url = URL(string: UIApplication.openSettingsURLString) else { return }
        UIApplication.shared.open(url)
    }
}

/// Staggered fade + rise for the header pieces; Reduce Motion gets a plain quick fade.
private struct HeaderEntrance: ViewModifier {
    let visible: Bool
    let delay: Double
    let reduceMotion: Bool

    func body(content: Content) -> some View {
        content
            .opacity(visible ? 1 : 0)
            .offset(y: visible || reduceMotion ? 0 : 10)
            .animation(
                reduceMotion
                    ? .easeOut(duration: 0.12)
                    : .spring(response: 0.45, dampingFraction: 0.82).delay(delay),
                value: visible
            )
    }
}

private extension View {
    func headerEntrance(visible: Bool, delay: Double, reduceMotion: Bool) -> some View {
        modifier(HeaderEntrance(visible: visible, delay: delay, reduceMotion: reduceMotion))
    }
}


// MARK: - How Community works

private enum CommunityGuide {
    static let pages: [FeatureGuidePage] = [
        .init(title: Copy.communityGuideCommunityTitle, body: Copy.communityGuideCommunityBody),
        .init(title: Copy.communityGuideAddTitle, body: Copy.communityGuideAddBody),
        .init(title: Copy.communityGuideTrackTitle, body: Copy.communityGuideTrackBody),
        .init(title: Copy.communityGuidePostTitle, body: Copy.communityGuidePostBody),
        .init(title: Copy.communityGuideReactTitle, body: Copy.communityGuideReactBody),
        .init(title: Copy.communityGuidePlaceTitle, body: Copy.communityGuidePlaceBody),
        .init(title: Copy.communityGuideSafetyTitle, body: Copy.communityGuideSafetyBody),
    ]
}

/// One drawing per `CommunityGuide.pages` entry, in the same order.
private struct CommunityGuideArt: View {
    let index: Int

    var body: some View {
        switch index {
        case 0: communityArt
        case 1: addPlaceArt
        case 2: myPlacesArt
        case 3: postArt
        case 4: reactArt
        case 5: placeArt
        default: safetyArt
        }
    }

    private var communityArt: some View {
        VStack(spacing: 14) {
            HStack(spacing: 8) {
                Image(systemName: "graduationcap.fill").foregroundStyle(Color.kicapSecondary)
                GuideBar(width: 90)
                Image(systemName: "chevron.down").font(.caption.weight(.bold)).foregroundStyle(Color.kicapSecondary)
            }
            .padding(.horizontal, 14)
            .frame(height: 40)
            .background(Color.nasiCream, in: Capsule())
            .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
            .guideTapHint(cornerRadius: 22)

            GuideMenu {
                GuideMenuRow(systemImage: "graduationcap.fill", highlighted: true) { GuideBar(width: 80) }
                GuideMenuRow(systemImage: "mappin.and.ellipse") { GuideBar(width: 64) }
                GuideMenuRow(systemImage: "globe.asia.australia.fill") { GuideBar(width: 72) }
            }
        }
    }

    private var addPlaceArt: some View {
        VStack(alignment: .trailing, spacing: 12) {
            Image(systemName: "plus")
                .font(.headline)
                .foregroundStyle(Color.kicap)
                .frame(width: 40, height: 40)
                .background(Color.nasiCream, in: Circle())
                .overlay(Circle().strokeBorder(Color.hairline, lineWidth: 1))
                .guideTapHint(cornerRadius: 22)
            GuideMenu {
                GuideMenuRow(systemImage: "plus", highlighted: true) { Text(Copy.communityAddPlaceMenuItem) }
                GuideMenuRow(systemImage: "list.bullet") { Text(Copy.communityMyPlacesMenuItem) }
            }
        }
    }

    private var myPlacesArt: some View {
        GuideMenu(width: 250) {
            GuideStatusRow(status: Copy.communityGuideStatusReview, tint: .kunyit)
            GuideStatusRow(status: Copy.communityGuideStatusLive, tint: .pandan)
            GuideStatusRow(status: Copy.communityGuideStatusLive, tint: .pandan)
        }
    }

    private var postArt: some View {
        VStack(spacing: 14) {
            VStack(alignment: .leading, spacing: 10) {
                GuideBar(width: 180)
                GuideBar(width: 130)
                Label(Copy.communityPostsTagPlace, systemImage: "mappin.and.ellipse")
                    .font(.makanBody(12).weight(.semibold))
                    .foregroundStyle(Color.sambalRed)
                    .padding(.horizontal, 10)
                    .frame(height: 28)
                    .background(Color.sambalRed.opacity(0.1), in: Capsule())
            }
            .padding(14)
            .frame(width: 240, alignment: .leading)
            .background(Color.nasiCream, in: .row)

            Label(Copy.communityPostsShareCTA, systemImage: "square.and.pencil")
                .font(.makanBody(14).weight(.semibold))
                .foregroundStyle(.white)
                .frame(width: 240, height: 44)
                .background(Color.sambalRed, in: .row)
                .guideTapHint(cornerRadius: 20)
        }
    }

    private var reactArt: some View {
        GuidePostCard {
            HStack(spacing: 8) {
                ForEach(Array(CommunityReactionType.allCases.enumerated()), id: \.element) { index, type in
                    let chip = HStack(spacing: 4) {
                        Text(type.emoji)
                        Text("\([3, 5, 2][index % 3])").monospacedDigit()
                    }
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap)
                    .padding(.horizontal, 10)
                    .frame(height: 30)
                    .background(Color.surface, in: Capsule())
                    .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
                    if type == .fire {
                        chip.guideTapHint(cornerRadius: 18)
                    } else {
                        chip
                    }
                }
                Spacer(minLength: 0)
                Image(systemName: "bubble.left")
                    .foregroundStyle(Color.kicapSecondary)
            }
        }
    }

    private var placeArt: some View {
        VStack(alignment: .leading, spacing: 12) {
            GuideBar(width: 150, height: 12)
            HStack(spacing: 6) {
                Image(systemName: "star.fill").foregroundStyle(Color.kunyit)
                GuideBar(width: 60)
            }
            .font(.caption)
            HStack(spacing: 18) {
                ForEach(["arrow.triangle.turn.up.right.circle.fill", "phone.fill", "camera.fill", "menucard"], id: \.self) { symbol in
                    Image(systemName: symbol)
                        .font(.title3)
                        .foregroundStyle(Color.sambalRed)
                        .frame(width: 40, height: 40)
                        .background(Color.sambalRed.opacity(0.1), in: Circle())
                }
            }
        }
        .padding(16)
        .frame(width: 260, alignment: .leading)
        .background(Color.nasiCream, in: .row)
        .guideTapHint(cornerRadius: 22)
    }

    private var safetyArt: some View {
        VStack(alignment: .trailing, spacing: 10) {
            GuidePostCard(menuHighlighted: true) {
                GuideBar(width: 120)
            }
            GuideMenu {
                GuideMenuRow(systemImage: "flag") { Text(Copy.communityGuideReport) }
                GuideMenuRow(systemImage: "hand.raised") { Text(Copy.communityGuideBlock) }
            }
            .padding(.trailing, 24)
        }
    }
}

private struct GuideStatusRow: View {
    let status: String
    let tint: Color

    var body: some View {
        HStack {
            GuideBar(width: 100)
            Spacer()
            Text(status)
                .font(.makanBody(12).weight(.semibold))
                .foregroundStyle(Color.kicap)
                .padding(.horizontal, 10)
                .frame(height: 26)
                .background(tint.opacity(0.25), in: Capsule())
        }
        .padding(.horizontal, 10)
        .frame(height: 44)
    }
}

/// A post in miniature: avatar, name bar, two lines, then whatever the page is teaching.
private struct GuidePostCard<Footer: View>: View {
    var menuHighlighted = false
    @ViewBuilder var footer: () -> Footer

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 8) {
                Circle().fill(Color.hairline).frame(width: 26, height: 26)
                GuideBar(width: 80)
                Spacer()
                let dots = Image(systemName: "ellipsis")
                    .foregroundStyle(Color.kicapSecondary)
                    .frame(width: 30, height: 26)
                if menuHighlighted {
                    dots.guideTapHint(cornerRadius: 14)
                } else {
                    dots
                }
            }
            GuideBar(width: 200)
            GuideBar(width: 150)
            footer()
        }
        .padding(14)
        .frame(width: 270, alignment: .leading)
        .background(Color.nasiCream, in: .row)
    }
}
