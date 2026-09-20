import SwiftUI

/// Loads a remote image with an explicit short timeout and a manual retry affordance —
/// unlike bare `AsyncImage`, `.empty` (still loading) and `.failure` are never conflated into
/// the same placeholder, so a dead/expired URL doesn't look identical to "still fetching".
struct RemoteImage<Placeholder: View>: View {
    let url: URL?
    var timeout: TimeInterval = 10
    @ViewBuilder var placeholder: () -> Placeholder

    @State private var phase: Phase = .idle
    @State private var loadTask: Task<Void, Never>?

    private enum Phase {
        case idle
        case loading
        case success(Image)
        case failure
    }

    var body: some View {
        Group {
            switch phase {
            case .success(let image):
                image.resizable()
            case .loading, .idle:
                ZStack {
                    placeholder()
                    ProgressView()
                }
            case .failure:
                ZStack {
                    placeholder()
                    Button {
                        load()
                    } label: {
                        Image(systemName: "arrow.clockwise.circle.fill")
                            .font(.system(size: 22))
                            .foregroundStyle(.white, Color.kicap.opacity(0.55))
                    }
                }
            }
        }
        .onAppear { load() }
        .onChange(of: url) { _, _ in load() }
        .onDisappear { loadTask?.cancel() }
    }

    private func load() {
        loadTask?.cancel()
        guard let url else {
            phase = .failure
            return
        }
        phase = .loading
        loadTask = Task {
            var request = URLRequest(url: url)
            request.timeoutInterval = timeout
            do {
                let (data, response) = try await URLSession.shared.data(for: request)
                guard !Task.isCancelled else { return }
                guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode),
                      let uiImage = UIImage(data: data) else {
                    phase = .failure
                    return
                }
                phase = .success(Image(uiImage: uiImage))
            } catch {
                guard !Task.isCancelled else { return }
                phase = .failure
            }
        }
    }
}
