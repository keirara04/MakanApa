import SwiftUI
import UIKit
import MapKit
import CoreLocation

private struct PressableCardStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1.0)
            .animation(.spring(response: 0.25, dampingFraction: 0.7), value: configuration.isPressed)
    }
}

struct HomeView: View {
    @Environment(AppRouter.self) private var router
    @Environment(LocationService.self) private var locationService
    @State private var showGengComingSoon = false
    @State private var showSettings = false
    private var recentStore = RecentDecisionStore.shared

    var body: some View {
        VStack(spacing: 24) {
            HStack {
                (Text("Makan").foregroundStyle(Color.kicap) + Text("Apa?").foregroundStyle(Color.sambalRed))
                    .font(.makanDisplay(20))

                Spacer()

                Button {
                    showSettings = true
                } label: {
                    Image(systemName: "gearshape.fill")
                        .foregroundStyle(.secondary)
                        .font(.system(size: 18))
                        .frame(width: 44, height: 44)
                        .contentShape(Rectangle())
                }
                .accessibilityLabel("Settings")
            }

            VStack(spacing: 6) {
                Text(Copy.homeGreeting)
                    .font(.makanDisplay(28))
                    .foregroundStyle(Color.kicap)
                Text(Copy.homeSubtext)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)
            }
            .padding(.top, 12)

            VStack(spacing: 16) {
                Button {
                    if case .authorized = locationService.state {
                        router.push(.soloPreferences)
                    } else {
                        router.push(.locationPermission)
                    }
                } label: {
                    HStack(spacing: 16) {
                        Image("SoloIllustration")
                            .resizable()
                            .scaledToFit()
                            .frame(width: 52, height: 52)
                            .accessibilityHidden(true)

                        VStack(alignment: .leading, spacing: 2) {
                            Text("SOLO").font(.makanDisplay(20))
                            Text("Pick for me").font(.makanBody(14))
                        }
                        .foregroundStyle(.white)

                        Spacer()

                        Image(systemName: "chevron.right")
                            .foregroundStyle(.white.opacity(0.8))
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 22)
                    .background(Color.sambalRed)
                    .clipShape(RoundedRectangle(cornerRadius: 30))
                    .shadow(color: Color.kicap.opacity(0.12), radius: 8, y: 4)
                }
                .buttonStyle(PressableCardStyle())

                Button {
                    showGengComingSoon = true
                } label: {
                    HStack(spacing: 16) {
                        Image("GengIllustration")
                            .resizable()
                            .scaledToFit()
                            .frame(width: 52, height: 52)
                            .accessibilityHidden(true)

                        VStack(alignment: .leading, spacing: 2) {
                            Text("GENG").font(.makanDisplay(20))
                            Text("Settle for us").font(.makanBody(14))
                        }
                        .foregroundStyle(Color.kicap.opacity(0.65))

                        Spacer()

                        Text("Soon")
                            .font(.makanBody(10))
                            .foregroundStyle(Color.kicap.opacity(0.5))
                            .padding(.horizontal, 8)
                            .padding(.vertical, 4)
                            .background(Color.kicap.opacity(0.08))
                            .clipShape(Capsule())

                        Image(systemName: "chevron.right")
                            .foregroundStyle(Color.kicap.opacity(0.3))
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 22)
                    .background(Color.kicap.opacity(0.08))
                    .clipShape(RoundedRectangle(cornerRadius: 30))
                    .shadow(color: Color.kicap.opacity(0.06), radius: 6, y: 3)
                }
                .buttonStyle(PressableCardStyle())
            }
            .padding(.horizontal)

            if !recentStore.decisions.isEmpty {
                recentSection
                    .padding(.horizontal)
            }

            Spacer()

            MascotView(mood: .idle, size: 180)
        }
        .padding()
        .frame(maxHeight: .infinity)
        .background(Color.nasiCream)
        .alert(Copy.gengComingSoon, isPresented: $showGengComingSoon) {
            Button("Okay", role: .cancel) {}
        }
        .sheet(isPresented: $showSettings) {
            SettingsView()
        }
    }

    // MARK: - Recent

    private var recentSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Recent")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)

            VStack(spacing: 8) {
                ForEach(recentStore.decisions.prefix(3)) { decision in
                    recentCard(for: decision)
                }
            }
        }
    }

    private func recentCard(for decision: RecentDecision) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            pickAgain(decision)
        } label: {
            HStack(spacing: 12) {
                Text(categoryEmoji(for: decision.foodCategory))
                    .font(.system(size: 22))

                VStack(alignment: .leading, spacing: 2) {
                    Text(decision.name)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)
                    Text("\(relativeDay(decision.timestamp)) · \(decision.source == "nearby" ? "Nearby" : "Decide")")
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                }

                Spacer()

                Image(systemName: "arrow.up.right")
                    .foregroundStyle(.secondary)
                    .font(.system(size: 13, weight: .semibold))
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .background(Color.kicap.opacity(0.05))
            .clipShape(RoundedRectangle(cornerRadius: 18))
        }
        .buttonStyle(PressableCardStyle())
    }

    private func pickAgain(_ decision: RecentDecision) {
        let coordinate = CLLocationCoordinate2D(latitude: decision.latitude, longitude: decision.longitude)
        let placemark = MKPlacemark(coordinate: coordinate)
        let mapItem = MKMapItem(placemark: placemark)
        mapItem.name = decision.name
        mapItem.openInMaps()
    }

    private func relativeDay(_ date: Date) -> String {
        if Calendar.current.isDateInToday(date) { return "Today" }
        if Calendar.current.isDateInYesterday(date) { return "Yesterday" }
        let formatter = RelativeDateTimeFormatter()
        formatter.dateTimeStyle = .named
        return formatter.localizedString(for: date, relativeTo: Date())
    }

    private func categoryEmoji(for foodCategory: String?) -> String {
        switch foodCategory {
        case "burger", "sandwich", "fast_food": return "🍔"
        case "chicken": return "🍗"
        case "pizza": return "🍕"
        case "ramen": return "🍜"
        case "sushi", "seafood": return "🍣"
        case "cafe", "breakfast", "drinks": return "☕️"
        case "dessert", "bakery": return "🍰"
        default: return "🍛"
        }
    }
}
