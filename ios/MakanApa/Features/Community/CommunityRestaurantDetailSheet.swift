import SwiftUI

/// Deliberately simpler than NearbyView's marker sheet — no photo carousel, no save/exclude
/// actions. Reuses the same `APIClient.placeDetails` call NearbyView already makes; this is a
/// read-only tap-through from the Community trending list, not a decision flow.
struct CommunityRestaurantDetailSheet: View {
    let item: CommunityFeedItem

    @State private var details: PlaceDetails?
    @State private var isLoading = true

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
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

                if let url = details?.placeGoogleMapsUrl.flatMap(URL.init(string:)) {
                    Link(destination: url) {
                        Text("Open in Google Maps")
                            .font(.makanBody(14))
                            .foregroundStyle(.white)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .background(Color.sambalRed)
                            .clipShape(Capsule())
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
        }
        .task { await loadDetails() }
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
