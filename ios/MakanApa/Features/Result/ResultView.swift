import SwiftUI
import MapKit
import UIKit

struct ResultView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var viewModel
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var showIntro = false
    @State private var showEmoji = false
    @State private var showHeadline = false
    @State private var showInfo = false
    @State private var showCTA = false

    var body: some View {
        VStack(spacing: 20) {
            MakanApaTopBar(onBack: { router.pop() })

            Spacer().frame(height: 20)

            if let error = viewModel.apiError {
                errorContent(for: error)
            } else if let pick = viewModel.currentPick {
                resultContent(for: pick)
            } else {
                MascotView(mood: .sad, caption: Copy.emptyState)
                    .padding()
            }

            Spacer()
        }
        .padding()
        .background(Color.nasiCream)
        .toolbar(.hidden, for: .navigationBar)
        .task(id: viewModel.currentPick?.id) {
            await runRevealSequence()
        }
        .onChange(of: viewModel.apiError == nil) { _, hasNoError in
            if !hasNoError {
                UINotificationFeedbackGenerator().notificationOccurred(.error)
            }
        }
    }

    // MARK: - Result

    @ViewBuilder
    private func resultContent(for pick: RecommendationResponse.Recommendation) -> some View {
        Text(Copy.resultIntro)
            .font(.makanBody(15))
            .foregroundStyle(.secondary)
            .opacity(showIntro ? 1 : 0)
            .animation(.easeOut(duration: 0.2), value: showIntro)

        VStack(spacing: 12) {
            Text(cuisineEmoji(for: pick))
                .font(.system(size: 48))
                .scaleEffect(showEmoji ? 1 : 0.4)
                .opacity(showEmoji ? 1 : 0)
                .animation(reduceMotion ? .easeOut(duration: 0.2) : .spring(response: 0.35, dampingFraction: 0.6), value: showEmoji)

            Text(pick.headline)
                .font(.makanDisplay(36))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
                .scaleEffect(showHeadline ? 1 : 0.85)
                .opacity(showHeadline ? 1 : 0)
                .animation(reduceMotion ? .easeOut(duration: 0.2) : .spring(response: 0.4, dampingFraction: 0.7), value: showHeadline)

            Text(Copy.resultThatsIt)
                .font(.makanBody(16))
                .foregroundStyle(.secondary)
                .opacity(showHeadline ? 1 : 0)
        }

        Divider().padding(.horizontal, 40)

        VStack(spacing: 6) {
            Text(pick.name)
                .font(.makanBody(16))
                .foregroundStyle(Color.kicap)

            HStack(spacing: 10) {
                if let rating = pick.rating {
                    Text("⭐ \(rating, specifier: "%.1f")")
                }
                if let priceLevel = pick.priceLevel {
                    Text(String(repeating: "RM ", count: priceLevel).trimmingCharacters(in: .whitespaces))
                }
                Text("🚶 ~\(walkingMinutes(for: pick.distanceKm)) min")
            }
            .font(.makanBody(13))
            .foregroundStyle(.secondary)
        }
        .offset(y: showInfo ? 0 : 12)
        .opacity(showInfo ? 1 : 0)
        .animation(.easeOut(duration: 0.25), value: showInfo)

        MakanPrimaryButton(title: Copy.jomMakan) {
            openInMaps(pick)
        }
        .padding(.horizontal)
        .padding(.top, 8)
        .offset(y: showCTA ? 0 : 16)
        .opacity(showCTA ? 1 : 0)
        .animation(.easeOut(duration: 0.25), value: showCTA)

        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            Task { await viewModel.reroll() }
        } label: {
            Text(Copy.pickAgain)
                .font(.makanBody(15))
                .foregroundStyle(.secondary)
        }
        .opacity(showCTA ? 1 : 0)

        MascotView(mood: .celebrate, size: 32)
            .opacity(showCTA ? 0.7 : 0)
    }

    private func runRevealSequence() async {
        showIntro = false; showEmoji = false; showHeadline = false; showInfo = false; showCTA = false
        guard viewModel.currentPick != nil else { return }

        if reduceMotion {
            showIntro = true; showEmoji = true; showHeadline = true; showInfo = true; showCTA = true
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            return
        }

        UINotificationFeedbackGenerator().notificationOccurred(.success)

        showIntro = true
        try? await Task.sleep(for: .milliseconds(100))
        guard !Task.isCancelled else { return }
        showEmoji = true
        try? await Task.sleep(for: .milliseconds(100))
        guard !Task.isCancelled else { return }
        showHeadline = true
        try? await Task.sleep(for: .milliseconds(150))
        guard !Task.isCancelled else { return }
        showInfo = true
        try? await Task.sleep(for: .milliseconds(150))
        guard !Task.isCancelled else { return }
        showCTA = true
    }

    // MARK: - Error

    @ViewBuilder
    private func errorContent(for error: APIError) -> some View {
        let copy = errorCopy(for: error)

        MascotView(mood: .sad, size: 72)

        VStack(spacing: 8) {
            Text(copy.headline)
                .font(.makanDisplay(28))
                .foregroundStyle(Color.kicap)
            Text(copy.detail)
                .font(.makanBody(14))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }

        MakanPrimaryButton(title: Copy.tryAgain) {
            Task { await viewModel.retry() }
        }
        .padding(.horizontal)
        .padding(.top, 8)

        Button {
            router.popToRoot()
        } label: {
            Text(Copy.backHome)
                .font(.makanBody(15))
                .foregroundStyle(.secondary)
        }
    }

    private func errorCopy(for error: APIError) -> (headline: String, detail: String) {
        switch error {
        case .transport:
            return (Copy.connectionErrorHeadline, Copy.connectionErrorDetail)
        default:
            return (Copy.genericAPIErrorHeadline, Copy.genericAPIErrorDetail)
        }
    }

    // MARK: - Helpers

    private func cuisineEmoji(for recommendation: RecommendationResponse.Recommendation) -> String {
        switch recommendation.cuisines.first {
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

    private func openInMaps(_ recommendation: RecommendationResponse.Recommendation) {
        Task {
            await viewModel.acceptCurrentPick()
        }
        let coordinate = CLLocationCoordinate2D(latitude: recommendation.latitude, longitude: recommendation.longitude)
        let placemark = MKPlacemark(coordinate: coordinate)
        let mapItem = MKMapItem(placemark: placemark)
        mapItem.name = recommendation.name
        mapItem.openInMaps()
    }
}
