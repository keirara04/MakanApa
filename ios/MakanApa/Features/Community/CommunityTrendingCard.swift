import SwiftUI

struct CommunityTrendingCard: View {
    let rank: Int
    let item: CommunityFeedItem
    let action: () -> Void

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var appeared = false

    var body: some View {
        Button(action: action) {
            HStack(alignment: .top, spacing: 12) {
                Text("\(rank)")
                    .font(.makanDisplay(rank <= 3 ? 20 : 15))
                    .foregroundStyle(rank <= 3 ? Color.sambalRed : .secondary)
                    .frame(width: 38, height: 38)
                    .background(Color.sambalRed.opacity(0.08), in: RoundedRectangle(cornerRadius: 12))

                VStack(alignment: .leading, spacing: 4) {
                    Text(item.name)
                        .font(.makanBody(16))
                        .foregroundStyle(Color.kicap)

                    if let subtext = subtext {
                        Text(subtext)
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                    }

                    HStack(spacing: 6) {
                        if let rating = item.rating {
                            Label(String(format: "%.1f", rating), systemImage: "star.fill")
                        }
                        if let spend = PricePresentation.approximateSpendLabel(for: item.priceLevel) {
                            Text("· \(spend)")
                        }
                        if let distanceKm = item.distanceKm {
                            Text("· \(String(format: "%.1f", distanceKm)) km")
                        }
                    }
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)

                    HStack(spacing: 8) {
                        if let pickerCount = item.pickerCount {
                            HStack(spacing: 3) {
                                Text("🔥")
                                Text("\(pickerCount)")
                                    .contentTransition(.numericText())
                                Text(pickerCount == 1 ? "person picked this" : "people picked this")
                            }
                            .font(.makanBody(12))
                            .foregroundStyle(Color.kicap.opacity(0.75))
                        }

                        if let vibe = item.trendingVibe {
                            VibeBadge(vibe: vibe)
                        }
                    }
                    .padding(.top, 2)
                }

                Spacer(minLength: 0)
                Image(systemName: "chevron.right")
                    .font(.system(size: 12, weight: .semibold))
                    .foregroundStyle(Color.kicap.opacity(0.35))
                    .padding(.top, 12)
            }
            .padding(18)
            .background(.white)
            .clipShape(RoundedRectangle(cornerRadius: 22))
            .overlay(RoundedRectangle(cornerRadius: 22).strokeBorder(Color.kicap.opacity(0.06)))
            .shadow(color: Color.kicap.opacity(0.04), radius: 12, y: 4)
        }
        .buttonStyle(CommunityPressStyle())
        .opacity(appeared ? 1 : 0)
        .offset(y: appeared || reduceMotion ? 0 : 6)
        .onAppear {
            let delay = reduceMotion ? 0 : Double(min(rank - 1, 3)) * 0.03
            withAnimation(.easeOut(duration: reduceMotion ? 0.12 : 0.22).delay(delay)) {
                appeared = true
            }
        }
    }

    private var subtext: String? {
        if let foodCategory = item.foodCategory, !foodCategory.isEmpty {
            return foodCategory
        }
        return item.cuisines.isEmpty ? nil : item.cuisines.joined(separator: " · ").capitalized
    }
}
