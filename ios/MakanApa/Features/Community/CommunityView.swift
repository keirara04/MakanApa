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
            }
            .padding(.horizontal, 20)
            .padding(.top, 12)
            .padding(.bottom, 32)
        }
        .scrollIndicators(.hidden)
        .refreshable { await attemptLoad() }
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
        .sheet(isPresented: $showingCommunityAssignment) {
            CommunityAssignmentSheet(currentUniversity: currentUniversity, currentArea: currentArea) {
                await attemptLoad()
            }
        }
    }

    // MARK: - Header

    /// A compact navigation row and a generous, locally relevant headline.
    private var header: some View {
        VStack(alignment: .leading, spacing: 22) {
            HStack(alignment: .center, spacing: 10) {
                Label("Community", systemImage: "person.2.fill")
                    .font(.system(.subheadline, design: .rounded, weight: .semibold))
                    .foregroundStyle(Color.kicap.opacity(0.7))
                    .accessibilityAddTraits(.isHeader)
                Spacer(minLength: 8)
                communityPill
                addMenu
            }
            .headerEntrance(visible: headerAppeared, delay: 0, reduceMotion: reduceMotion)

            VStack(alignment: .leading, spacing: 8) {
                Text(headline)
                    .font(.system(.largeTitle, design: .rounded, weight: .heavy))
                    .tracking(-1.1)
                    .foregroundStyle(Color.kicap)
                    .fixedSize(horizontal: false, vertical: true)
                    .headerEntrance(visible: headerAppeared, delay: 0.06, reduceMotion: reduceMotion)
                Text(subtitle)
                    .font(.system(.subheadline, design: .rounded))
                    .foregroundStyle(Color.kicap.opacity(0.62))
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

    /// Current community as one compact pill (tag + chevron). When the affiliation changes
    /// (university ↔ area ↔ public) the tag slides out/in and the pill gives a small bounce.
    private var communityPill: some View {
        let badge = CommunityBadge(affiliationType: currentAffiliationType, university: currentUniversity, area: currentArea)

        return Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            showingCommunityAssignment = true
        } label: {
            HStack(spacing: 5) {
                badge
                    .lineLimit(1)
                    .fixedSize()
                    .id(badge.label)
                    .transition(reduceMotion ? .opacity : .asymmetric(
                        insertion: .push(from: .bottom).combined(with: .opacity),
                        removal: .push(from: .top).combined(with: .opacity)
                    ))
                Image(systemName: "chevron.down")
                    .font(.system(size: 9, weight: .bold))
                    .foregroundStyle(Color.sambalRed)
            }
            .padding(.leading, 5)
            .padding(.trailing, 9)
            .padding(.vertical, 5)
            .background(Color.white, in: Capsule())
            .overlay(Capsule().stroke(Color.kicap.opacity(0.08), lineWidth: 1))
            .shadow(color: .black.opacity(0.05), radius: 3, y: 1)
            .clipShape(Capsule())
            .scaleEffect(pillBounce ? 1.06 : 1)
            .frame(minHeight: 44)
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
                .font(.system(size: 15, weight: .bold))
                .foregroundStyle(.white)
                .frame(width: 34, height: 34)
                .background(Color.sambalRed, in: Circle())
                .shadow(color: Color.sambalRed.opacity(0.3), radius: 4, y: 2)
                .frame(width: 44, height: 44)
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

    private var emptyState: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(alignment: .top, spacing: 14) {
                Image(systemName: "fork.knife.circle")
                    .font(.system(size: 30, weight: .light))
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 54, height: 54)
                    .background(Color.sambalRed.opacity(0.08), in: RoundedRectangle(cornerRadius: 17))
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 6) {
                    Text("Your next favourite starts here")
                        .font(.system(.headline, design: .rounded))
                        .foregroundStyle(Color.kicap)
                    Text("Know a spot worth sharing? Help your community discover it.")
                        .font(.system(.subheadline, design: .rounded))
                        .foregroundStyle(Color.kicap.opacity(0.65))
                        .fixedSize(horizontal: false, vertical: true)
                }
            }
            Button { showingAddPlace = true } label: {
                HStack {
                    Text("Add a good spot")
                    Spacer()
                    Image(systemName: "plus.circle.fill")
                }
                .font(.system(.subheadline, design: .rounded, weight: .semibold))
                .foregroundStyle(Color.sambalRed)
                .frame(minHeight: 44)
                .contentShape(Rectangle())
            }
            .buttonStyle(CommunityPressStyle())
        }
        .padding(20)
        .background(Color.white.opacity(0.5), in: RoundedRectangle(cornerRadius: 24))
        .overlay(RoundedRectangle(cornerRadius: 24).strokeBorder(Color.kicap.opacity(0.07)))
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
        switch error {
        case .transport:
            return Copy.connectionErrorDetail
        default:
            return Copy.genericAPIErrorDetail
        }
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
