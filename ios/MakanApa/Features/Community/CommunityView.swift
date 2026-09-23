import CoreLocation
import SwiftUI

struct CommunityView: View {
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var viewModel = CommunityViewModel()
    @State private var selectedItem: CommunityFeedItem?
    @State private var headerAppeared = false
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
            LazyVStack(alignment: .leading, spacing: 14) {
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
                        Text("🔥 TRENDING")
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                            .tracking(1)
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
            .padding(16)
        }
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

    private var header: some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack {
                Text("COMMUNITY")
                    .font(.makanBody(11))
                    .foregroundStyle(.secondary)
                    .tracking(1)
                Spacer()
                Button {
                    showingCommunityAssignment = true
                } label: {
                    HStack(spacing: 4) {
                        CommunityBadge(affiliationType: currentAffiliationType, university: currentUniversity, area: currentArea)
                        Text(currentAffiliationType == nil ? Copy.communityAssignCommunityCTA : Copy.communityChangeCommunityCTA)
                            .font(.makanBody(11))
                            .foregroundStyle(Color.sambalRed)
                    }
                }
                .buttonStyle(.plain)
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
                    Image(systemName: "plus.circle.fill")
                        .font(.system(size: 20))
                        .foregroundStyle(Color.sambalRed)
                }
            }
            Text(headline)
                .font(.makanDisplay(22))
                .foregroundStyle(Color.kicap)
            Text(subtitle)
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
        }
        .opacity(headerAppeared ? 1 : 0)
        .offset(y: headerAppeared ? 0 : 8)
        .onAppear {
            withAnimation(.easeOut(duration: reduceMotion ? 0.12 : 0.22)) {
                headerAppeared = true
            }
        }
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
                Text(String(format: Copy.communityPostsSectionFormat, communityShortName.uppercased()))
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
                    .tracking(1)
                Spacer()
                if !postStore.posts.isEmpty {
                    Button(Copy.communityPostsSeeAll) { showingAllPosts = true }
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .frame(minHeight: 32)
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
                            .font(.makanBody(14))
                            .foregroundStyle(Color.sambalRed)
                            .frame(maxWidth: .infinity, minHeight: 44)
                            .background(Color.sambalRed.opacity(0.08))
                            .clipShape(RoundedRectangle(cornerRadius: 14))
                    }
                    .buttonStyle(.plain)
                }
            }
        }
        .padding(.bottom, 6)
    }

    // MARK: - New in your area

    private func newInAreaSection(_ items: [CommunityFeedItem]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("NEW IN YOUR AREA")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
                .tracking(1)

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
        VStack(spacing: 8) {
            Text(Copy.communityEmptyHeadline)
                .font(.makanBody(16))
                .foregroundStyle(Color.kicap)
            Text(Copy.communityEmptyDetail)
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            Button(Copy.communityAddPlaceCTA) { showingAddPlace = true }
                .font(.makanBody(14))
                .padding(.top, 4)
        }
        .frame(maxWidth: .infinity)
        .padding(.top, 40)
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
