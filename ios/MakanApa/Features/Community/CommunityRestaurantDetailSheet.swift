import SwiftUI

/// Deliberately simpler than NearbyView's marker sheet — no photo carousel, no save/exclude
/// actions. Reuses the same `APIClient.placeDetails` call NearbyView already makes; this is a
/// read-only tap-through from the Community trending list, not a decision flow. Sections render
/// progressively based on what's actually known — an empty section (no phone, no menu) simply
/// doesn't appear, rather than looking incomplete.
struct CommunityRestaurantDetailSheet: View {
    let item: CommunityFeedItem

    @State private var details: PlaceDetails?
    @State private var isLoading = true
    @State private var communityPosts: [CommunityPost] = []
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                header
                actionRow
                if let details, !details.communityPhotos.isEmpty {
                    VStack(alignment: .leading, spacing: 8) {
                        sectionHeader("PHOTOS")
                        PlaceCommunityPhotoStrip(urls: details.communityPhotos)
                    }
                } else if let details, details.photos.isEmpty, !isLoading {
                    QuickAddPhotoRow(restaurantId: item.id)
                }
                if !communityPosts.isEmpty {
                    communitySaysSection
                }
                if let details {
                    aboutSection(details)
                }
                if let halal = details?.halal {
                    HalalVerificationSection(restaurantId: item.id, restaurantName: item.name, halal: halal) {
                        Task { await loadDetails() }
                    }
                }
                if let details, !details.menuItems.isEmpty {
                    VStack(alignment: .leading, spacing: 8) {
                        sectionHeader("MENU")
                        PlaceMenuSection(items: details.menuItems)
                    }
                }
                if isLoading {
                    HStack {
                        Spacer()
                        ProgressView()
                        Spacer()
                    }
                    .padding(.top, 8)
                } else if let details, !details.reviews.isEmpty {
                    reviewsSection(for: details)
                }
            }
            .padding(20)
            .opacity(isLoading ? 0.6 : 1)
            .animation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.2), value: isLoading)
        }
        .task { await loadDetails() }
        .task { await loadCommunityPosts() }
    }

    /// Read-only — no reactions/menus here (this sheet has no navigation stack to open a thread
    /// in). Hidden entirely for unaffiliated users, whose board is always empty anyway.
    private var communitySaysSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            sectionHeader(String(format: Copy.communityPostsSectionFormat, communityShortName.uppercased()))
            ForEach(communityPosts) { post in
                VStack(alignment: .leading, spacing: 4) {
                    HStack(spacing: 6) {
                        Image(AvatarCharacter(key: post.author.avatarKey).imageName)
                            .resizable()
                            .scaledToFill()
                            .frame(width: 22, height: 22)
                            .clipShape(Circle())
                            .accessibilityHidden(true)
                        Text(post.author.name)
                            .font(.makanBody(12))
                            .foregroundStyle(Color.kicap)
                        if let date = post.createdDate {
                            Text("· \(date, format: .relative(presentation: .named, unitsStyle: .abbreviated))")
                                .font(.makanBody(12))
                                .foregroundStyle(.secondary)
                        }
                    }
                    Text(post.body)
                        .font(.makanBody(13))
                        .foregroundStyle(Color.kicap.opacity(0.9))
                        .fixedSize(horizontal: false, vertical: true)
                }
                .accessibilityElement(children: .combine)
            }
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }

    private var communityShortName: String {
        guard case .authenticated(let user) = AuthStore.shared.session else { return "your community" }
        return (user.affiliationType == "university" ? user.university : user.area) ?? "your community"
    }

    @MainActor
    private func loadCommunityPosts() async {
        guard case .authenticated(let user) = AuthStore.shared.session,
              user.affiliationType == "university" || user.affiliationType == "area" else { return }
        communityPosts = (try? await APIClient.communityPosts(restaurantId: item.id, limit: 2).posts) ?? []
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(item.name)
                .font(.makanDisplay(20))
                .foregroundStyle(Color.kicap)

            HStack(spacing: 6) {
                if let rating = item.rating {
                    Label(String(format: "%.1f", rating), systemImage: "star.fill")
                        .foregroundStyle(Color.kunyit)
                }
                if let spend = PricePresentation.approximateSpendLabel(for: item.priceLevel) {
                    Text("· \(spend)")
                }
                Text(item.openStatus == "open" ? "· Open" : item.openStatus == "closed" ? "· Closed" : "")
            }
            .font(.makanBody(13))
            .foregroundStyle(.secondary)

            if !item.cuisines.isEmpty {
                Text(item.cuisines.joined(separator: " · ").capitalized)
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
            }
        }
    }

    private var actionRow: some View {
        HStack(spacing: 10) {
            if let url = details?.placeGoogleMapsUrl.flatMap(URL.init(string:)) {
                Link(destination: url) {
                    actionLabel(title: "Directions", systemImage: "arrow.triangle.turn.up.right.circle.fill")
                }
            }
            if let phone = details?.phone, let url = URL(string: "tel:\(phone.filter(\.isNumber))") {
                Link(destination: url) {
                    actionLabel(title: "Call", systemImage: "phone.fill")
                }
            }
            if let handle = details?.instagramHandle, let url = URL(string: "https://instagram.com/\(handle)") {
                Link(destination: url) {
                    actionLabel(title: "Instagram", systemImage: "camera.fill")
                }
            }
        }
    }

    private func actionLabel(title: String, systemImage: String) -> some View {
        VStack(spacing: 4) {
            Image(systemName: systemImage).font(.system(size: 18))
            Text(title).font(.makanBody(11))
        }
        .foregroundStyle(Color.kicap)
        .frame(maxWidth: .infinity)
        .padding(.vertical, 10)
        .background(Color.kicap.opacity(0.06))
        .clipShape(RoundedRectangle(cornerRadius: 14))
    }

    private func aboutSection(_ details: PlaceDetails) -> some View {
        let rows: [(String, String)] = [
            details.closesAt.map { ("🕐", "Open until \($0)") },
            details.phone.map { ("☎️", $0) },
            details.websiteUrl.map { ("🌐", $0) },
        ].compactMap { $0 }

        return Group {
            if !rows.isEmpty {
                VStack(alignment: .leading, spacing: 8) {
                    sectionHeader("ABOUT")
                    ForEach(rows, id: \.1) { emoji, text in
                        HStack(spacing: 8) {
                            Text(emoji)
                            Text(text).font(.makanBody(13)).foregroundStyle(Color.kicap.opacity(0.85))
                        }
                    }
                }
            }
        }
    }

    private func sectionHeader(_ text: String) -> some View {
        Text(text)
            .font(.makanBody(11))
            .foregroundStyle(.secondary)
            .tracking(0.5)
    }

    @MainActor
    private func loadDetails() async {
        isLoading = true
        defer { isLoading = false }
        details = try? await APIClient.placeDetails(restaurantId: item.id)
    }

    @ViewBuilder
    private func reviewsSection(for details: PlaceDetails) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            sectionHeader("COMMUNITY")
            Text("Reviews from Google Maps")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)

            ForEach(Array(details.reviews.prefix(2).enumerated()), id: \.offset) { _, review in
                VStack(alignment: .leading, spacing: 4) {
                    if let rating = review.rating {
                        Text(String(repeating: "★", count: Int(rating.rounded())))
                            .font(.system(size: 11))
                            .foregroundStyle(Color.kunyit)
                    }
                    Text("\"\(review.text.count > 120 ? String(review.text.prefix(120)).trimmingCharacters(in: .whitespaces) + "…" : review.text)\"")
                        .font(.makanBody(13))
                        .italic()
                        .foregroundStyle(Color.kicap.opacity(0.85))
                    Text("— \(review.authorName)")
                        .font(.makanBody(11))
                        .foregroundStyle(.secondary)
                }
            }
        }
        .padding(14)
        .background(Color.kicap.opacity(0.04))
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }
}
