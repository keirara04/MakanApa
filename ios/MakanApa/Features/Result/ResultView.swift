import SwiftUI
import MapKit

struct ResultView: View {
    @Environment(SoloViewModel.self) private var viewModel

    var body: some View {
        VStack(spacing: 24) {
            if let pick = viewModel.currentPick {
                Text(pick.restaurant.name.uppercased())
                    .font(.largeTitle.bold())
                    .multilineTextAlignment(.center)

                Text("No overthinking today.")
                    .foregroundStyle(.secondary)

                RestaurantCard(restaurant: pick.restaurant)
                    .padding(.horizontal)

                Button {
                    openInMaps(pick.restaurant)
                } label: {
                    Text("JOM →")
                        .font(.headline)
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .padding(.horizontal)

                Button("Nah, pick again") {
                    viewModel.reroll()
                }
            } else {
                Text("No restaurants matched. Try widening your budget or distance.")
                    .multilineTextAlignment(.center)
                    .foregroundStyle(.secondary)
                    .padding()
            }
        }
        .padding()
        .navigationTitle("MakanApa?")
    }

    private func openInMaps(_ restaurant: Restaurant) {
        let coordinate = CLLocationCoordinate2D(latitude: restaurant.latitude, longitude: restaurant.longitude)
        let placemark = MKPlacemark(coordinate: coordinate)
        let mapItem = MKMapItem(placemark: placemark)
        mapItem.name = restaurant.name
        mapItem.openInMaps()
    }
}
