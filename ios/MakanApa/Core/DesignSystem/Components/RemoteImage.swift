import ImageIO
import SwiftUI

/// Loads a remote image with an explicit short timeout and a manual retry affordance —
/// unlike bare `AsyncImage`, `.empty` (still loading) and `.failure` are never conflated into
/// the same placeholder, so a dead/expired URL doesn't look identical to "still fetching".
struct RemoteImage<Placeholder: View>: View {
    let url: URL?
    var timeout: TimeInterval = 10
    /// Longest side in pixels after decoding — a full-width card on a Pro Max is ~1290px.
    var maxPixelSize: CGFloat = 1290
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

        if let cached = ImageMemoryCache.image(for: url) {
            phase = .success(Image(uiImage: cached))
            return
        }

        phase = .loading
        loadTask = Task { [timeout, maxPixelSize] in
            // Network + decode run off the main actor — decoding a full JPEG on main was a
            // visible hitch while swiping the photo carousel.
            let uiImage = await Task.detached(priority: .userInitiated) {
                await Self.fetchDownsampled(url: url, timeout: timeout, maxPixelSize: maxPixelSize)
            }.value
            guard !Task.isCancelled else { return }
            guard let uiImage else {
                phase = .failure
                return
            }
            ImageMemoryCache.store(uiImage, for: url)
            phase = .success(Image(uiImage: uiImage))
        }
    }

    /// Decodes straight to a bitmap no larger than `maxPixelSize` on its longest side, already
    /// prepared for display — never the full-size image first.
    private static func fetchDownsampled(url: URL, timeout: TimeInterval, maxPixelSize: CGFloat) async -> UIImage? {
        var request = URLRequest(url: url)
        request.timeoutInterval = timeout
        guard let (data, response) = try? await URLSession.shared.data(for: request),
              let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode),
              let source = CGImageSourceCreateWithData(data as CFData, [kCGImageSourceShouldCache: false] as CFDictionary)
        else { return nil }

        let options: [CFString: Any] = [
            kCGImageSourceCreateThumbnailFromImageAlways: true,
            kCGImageSourceCreateThumbnailWithTransform: true,
            kCGImageSourceShouldCacheImmediately: true,
            kCGImageSourceThumbnailMaxPixelSize: maxPixelSize,
        ]
        guard let cgImage = CGImageSourceCreateThumbnailAtIndex(source, 0, options as CFDictionary) else { return nil }
        return UIImage(cgImage: cgImage)
    }
}
