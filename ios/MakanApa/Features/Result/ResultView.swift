import SwiftUI
import MapKit
import UIKit

struct ResultView: View {
    @Environment(SoloViewModel.self) private var viewModel
    @State private var isRevealed = false

    var body: some View {
        VStack(spacing: 20) {
            MakanApaTopBar()

            Spacer()

            if let pick = viewModel.currentPick {
                Text(Copy.resultIntro)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)

                VStack(spacing: 12) {
                    Text(cuisineEmoji(for: pick.restaurant))
                        .font(.system(size: 48))

                    Text(headline(for: pick.restaurant))
                        .font(.makanDisplay(36))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.center)

                    Text(Copy.resultThatsIt)
                        .font(.makanBody(16))
                        .foregroundStyle(.secondary)
                }
                .scaleEffect(isRevealed ? 1 : 0.7)
                .opacity(isRevealed ? 1 : 0)

                Divider().padding(.horizontal, 40)

                VStack(spacing: 6) {
                    Text(pick.restaurant.name)
                        .font(.makanBody(16))
                        .foregroundStyle(Color.kicap)

                    HStack(spacing: 10) {
                        if let rating = pick.restaurant.rating {
                            Text("⭐ \(rating, specifier: "%.1f")")
                        }
                        if let priceLevel = pick.restaurant.priceLevel {
                            Text(String(repeating: "RM ", count: priceLevel).trimmingCharacters(in: .whitespaces))
                        }
                        Text("🚶 ~\(walkingMinutes(for: pick.distanceKm)) min")
                    }
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                }

                MakanPrimaryButton(title: Copy.jomMakan) {
                    openInMaps(pick.restaurant)
                }
                .padding(.horizontal)
                .padding(.top, 8)

                Button {
                    UIImpactFeedbackGenerator(style: .light).impactOccurred()
                    isRevealed = false
                    viewModel.reroll()
                    revealSoon()
                } label: {
                    Text(Copy.pickAgain)
                        .font(.makanBody(15))
                        .foregroundStyle(.secondary)
                }

                Text("🍚")
                    .font(.system(size: 22))
                    .opacity(0.6)
            } else {
                MascotLine(caption: Copy.emptyState)
                    .padding()
            }

            Spacer()
        }
        .padding()
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
        .onAppear {
            revealSoon()
        }
    }

    private func revealSoon() {
        withAnimation(.spring(response: 0.4, dampingFraction: 0.65)) {
            isRevealed = true
        }
        UINotificationFeedbackGenerator().notificationOccurred(.success)
    }

    private func headline(for restaurant: Restaurant) -> String {
        if let signatureDish = restaurant.signatureDish {
            return signatureDish.uppercased() + "."
        }
        return (restaurant.cuisines.first.map(cuisineLabel) ?? "MAKAN") + "."
    }

    private func cuisineLabel(_ cuisine: String) -> String {
        cuisine.uppercased()
    }

    private func cuisineEmoji(for restaurant: Restaurant) -> String {
        switch restaurant.cuisines.first {
        case "malay": return "🍛"
        case "chinese": return "🍜"
        case "japanese": return "🍣"
        case "korean": return "🍗"
        case "thai": return "🌶️"
        case "western": return "🍝"
        case "indian": return "🍛"
        default: return "🍽️"
        }
    }

    private func walkingMinutes(for distanceKm: Double) -> Int {
        max(1, Int((distanceKm * 12).rounded()))
    }

    private func openInMaps(_ restaurant: Restaurant) {
        let coordinate = CLLocationCoordinate2D(latitude: restaurant.latitude, longitude: restaurant.longitude)
        let placemark = MKPlacemark(coordinate: coordinate)
        let mapItem = MKMapItem(placemark: placemark)
        mapItem.name = restaurant.name
        mapItem.openInMaps()
    }
}
