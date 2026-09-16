import SwiftUI

struct RestaurantCard: View {
    let restaurant: Restaurant

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Label(restaurant.name, systemImage: "mappin.circle.fill")
                .font(.headline)
            if let rating = restaurant.rating {
                Label("\(rating, specifier: "%.1f")", systemImage: "star.fill")
            }
            if let priceLevel = restaurant.priceLevel {
                Label(String(repeating: "RM", count: 1) + String(repeating: "$", count: priceLevel), systemImage: "banknote")
            }
            if let address = restaurant.address {
                Label(address, systemImage: "location")
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
            }
        }
        .padding()
        .background(Color.secondary.opacity(0.1))
        .clipShape(RoundedRectangle(cornerRadius: 16))
    }
}
