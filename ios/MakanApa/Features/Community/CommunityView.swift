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

    var body: some View {
        Group {
            if viewModel.isUniversityUser {
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
    }

    private var content: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: 14) {
                header

                if viewModel.isLoading && viewModel.feed == nil {
                    skeletonRows
                } else if let feed = viewModel.feed {
                    if feed.trending.isEmpty {
                        emptyState
                    } else {
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
                if let community = viewModel.feed?.community {
                    CommunityBadge(affiliationType: community.type, university: community.university)
                }
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

    private var headline: String {
        if let community = viewModel.feed?.community, community.type == "university", let university = community.university {
            return String(format: Copy.communityHeadlineUniversityFormat, university)
        }
        return Copy.communityHeadlinePublic
    }

    private var subtitle: String {
        if let community = viewModel.feed?.community, community.type == "university", let university = community.university {
            return String(format: Copy.communitySubtitleUniversityFormat, university)
        }
        return Copy.communitySubtitlePublic
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
        if viewModel.isUniversityUser {
            await viewModel.load(coordinate: currentCoordinate)
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
