import SwiftUI
import UIKit

/// "Send to geng" — shares a place with the server-built link. WhatsApp is where most makan
/// plans get made, so when it's installed the main button goes straight there; the system share
/// sheet is always one tap away (the small share icon), so a WhatsApp hiccup is never a dead end.
/// Without WhatsApp the main button *is* the system share sheet.
struct SendToGengButton: View {
    let restaurantId: Int
    let shareUrl: String
    /// "Jom makan sini? 🍛 KFC — Jalan Reko, Kajang (1.2 km)"
    let message: String
    /// Called once per share attempt (funnel event / decision interaction).
    var onShare: () -> Void = {}

    @Environment(\.openURL) private var openURL

    private var fullText: String { "\(message)\n\(shareUrl)" }

    private var whatsAppURL: URL? {
        var components = URLComponents()
        components.scheme = "whatsapp"
        components.host = "send"
        components.queryItems = [URLQueryItem(name: "text", value: fullText)]
        guard let url = components.url, UIApplication.shared.canOpenURL(url) else { return nil }
        return url
    }

    var body: some View {
        HStack(spacing: 8) {
            if let whatsAppURL {
                Button {
                    recordShare()
                    openURL(whatsAppURL)
                } label: {
                    label(title: "Send to geng", systemImage: "paperplane.fill")
                }
                .accessibilityHint("Opens WhatsApp with this place")

                ShareLink(item: fullText) {
                    Image(systemName: "square.and.arrow.up")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundStyle(Color.kicap)
                        .frame(width: 48, height: 48)
                        .background(Color.kicap.opacity(0.06))
                        .clipShape(Circle())
                }
                .simultaneousGesture(TapGesture().onEnded { recordShare() })
                .accessibilityLabel("More ways to share")
            } else {
                ShareLink(item: fullText) {
                    label(title: "Send to geng", systemImage: "square.and.arrow.up")
                }
                .simultaneousGesture(TapGesture().onEnded { recordShare() })
            }
        }
    }

    private func label(title: String, systemImage: String) -> some View {
        Label(title, systemImage: systemImage)
            .font(.makanBody(14))
            .foregroundStyle(Color.kicap)
            .frame(maxWidth: .infinity)
            .frame(minHeight: 48)
            .background(Color.kicap.opacity(0.06))
            .clipShape(Capsule())
    }

    private func recordShare() {
        onShare()
        let id = restaurantId
        Task { _ = try? await APIClient.shareStarted(restaurantId: id) }
    }

    /// The one-line message every surface uses, e.g. "Jom makan sini? 🍛 KFC — Jalan Reko, Kajang (1.2 km)".
    static func message(name: String, whereText: String?, distanceKm: Double?) -> String {
        var line = "Jom makan sini? 🍛 \(name)"
        if let whereText, !whereText.isEmpty { line += " — \(whereText)" }
        if let distanceKm { line += String(format: " (%.1f km)", distanceKm) }
        return line
    }
}
