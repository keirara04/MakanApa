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
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                header
                actionRow
                if let details, !details.communityPhotos.isEmpty {
                    photosSection(details.communityPhotos)
                }
                if let details {
                    aboutSection(details)
                }
                if let details, !details.menuItems.isEmpty {
                    menuSection(details.menuItems)
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

    private func photosSection(_ urls: [String]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            sectionHeader("PHOTOS")
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 10) {
                    ForEach(urls, id: \.self) { urlString in
                        AsyncImage(url: URL(string: urlString)) { phase in
                            switch phase {
                            case .success(let image):
                                image.resizable().scaledToFill()
                            default:
                                Color.kicap.opacity(0.06)
                            }
                        }
                        .frame(width: 140, height: 140)
                        .clipShape(RoundedRectangle(cornerRadius: 14))
                        .transition(.opacity)
                    }
                }
            }
            Text("Shared by the MakanApa community")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
        }
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

    private func menuSection(_ items: [MenuItem]) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            sectionHeader("MENU")
            ForEach(items) { item in
                HStack {
                    Text(item.name).font(.makanBody(14)).foregroundStyle(Color.kicap)
                    Spacer()
                    if let price = item.price {
                        Text("RM\(price, specifier: "%.2f")").font(.makanBody(13)).foregroundStyle(.secondary)
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
