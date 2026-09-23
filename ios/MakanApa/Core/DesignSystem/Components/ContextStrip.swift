import SwiftUI

/// "Right now ☔ Hujan · 🌙 Supper" — the context Makan Brain is currently weighing, made
/// visible and switchable. Tapping a chip turns that signal off (or back on) for future picks.
/// Renders nothing when there's no active context, so it never takes space for no reason.
struct ContextStrip: View {
    @Environment(LocationService.self) private var locationService
    @State private var signals: [ContextSignal] = []

    var body: some View {
        Group {
            if !signals.isEmpty {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        Text("Right now")
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                        ForEach(signals) { signal in
                            chip(signal)
                        }
                    }
                    .padding(.horizontal, 2)
                }
                .transition(.opacity)
            }
        }
        .task(id: coordinateKey) { await load() }
    }

    private func chip(_ signal: ContextSignal) -> some View {
        let off = signal.ignored
        return Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            ContextPreferences.toggle(signal.key)
            Task { await load() }
        } label: {
            HStack(spacing: 4) {
                Text(signal.icon).accessibilityHidden(true)
                Text(signal.label)
                    .strikethrough(off)
            }
            .font(.makanBody(13))
            .foregroundStyle(off ? .secondary : Color.kicap)
            .padding(.horizontal, 10)
            .frame(minHeight: 32)
            .background(off ? Color.clear : Color.kunyit.opacity(signal.stale ? 0.12 : 0.25))
            .overlay(Capsule().stroke(Color.kicap.opacity(off ? 0.2 : 0), lineWidth: 1))
            .clipShape(Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityLabel("\(signal.label), \(off ? "ignored" : "affecting picks")")
        .accessibilityHint(off ? "Double tap to let this affect picks again" : "Double tap to stop this affecting picks")
    }

    private var coordinateKey: String {
        if case .authorized(let c) = locationService.state {
            return String(format: "%.2f,%.2f", c.latitude, c.longitude)
        }
        return "none"
    }

    private func load() async {
        guard case .authorized(let c) = locationService.state,
              let response = try? await APIClient.context(latitude: c.latitude, longitude: c.longitude) else { return }
        withAnimation(Motion.quick) { signals = response.signals }
    }
}
