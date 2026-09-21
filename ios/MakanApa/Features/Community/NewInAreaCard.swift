import SwiftUI

/// Compact horizontal card for the "New in your area" rail — deliberately lighter than
/// `CommunityTrendingCard` (no rank, no pick count) since these places have no pick history
/// yet; a "Just added" badge signals freshness instead.
struct NewInAreaCard: View {
    let item: CommunityFeedItem
    let action: () -> Void

    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 6) {
                Text("✨ New")
                    .font(.makanBody(10))
                    .foregroundStyle(Color.pandan)

                Text(item.name)
                    .font(.makanBody(14))
                    .foregroundStyle(Color.kicap)
                    .lineLimit(1)

                if let subtext {
                    Text(subtext)
                        .font(.makanBody(11))
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }

                HStack(spacing: 6) {
                    if let rating = item.rating {
                        Label(String(format: "%.1f", rating), systemImage: "star.fill")
                    }
                    if let distanceKm = item.distanceKm {
                        Text("· \(String(format: "%.1f", distanceKm)) km")
                    }
                }
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
            }
            .padding(12)
            .frame(width: 160, alignment: .leading)
            .background(.white)
            .clipShape(RoundedRectangle(cornerRadius: 16))
            .shadow(color: .black.opacity(0.05), radius: 5, y: 2)
        }
        .buttonStyle(.plain)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(item.name), newly added\(item.rating.map { ", rated \(String(format: "%.1f", $0))" } ?? "")")
    }

    private var subtext: String? {
        if let foodCategory = item.foodCategory, !foodCategory.isEmpty {
            return foodCategory
        }
        return item.cuisines.isEmpty ? nil : item.cuisines.joined(separator: " · ").capitalized
    }
}
