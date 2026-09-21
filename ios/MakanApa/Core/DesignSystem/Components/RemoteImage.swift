import ImageIO
import SwiftUI

/// Whether a loaded image may be kept in `ImageMemoryCache`. Deliberately opt-in (default
/// `.noStore`) rather than inferred from the URL — Google's Places policy prohibits persisting
/// Places photo content, so a call site must explicitly prove a photo is MakanApa's own
/// (community/S3) before it can request `.memory`. See `NearbyView.placePhoto(for:)` for the one
/// call site that has to choose between the two per-photo.
enum ImageCachePolicy: Equatable {
    case memory(key: String)
    case noStore
}

/// Where a photo came from — the model's job to know and tell the view, not something a view
/// should infer from a URL's host/path at the point it needs a cache policy.
enum PhotoSource: Equatable {
    case google
    case community
}

/// Loads a remote image with an explicit short timeout and a manual retry affordance —
/// unlike bare `AsyncImage`, `.empty` (still loading) and `.failure` are never conflated into
/// the same placeholder, so a dead/expired URL doesn't look identical to "still fetching".
struct RemoteImage<Placeholder: View>: View {
    let url: URL?
    var timeout: TimeInterval = 10
    var cachePolicy: ImageCachePolicy = .noStore
    /// When set, decodes at roughly this size instead of full resolution — a 3000x3000 photo
    /// decodes to ~36MB of RGBA regardless of its on-screen size, so this matters far more than
    /// caching for peak memory. Point size; multiplied by the screen scale at decode time.
    var targetSize: CGSize?
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

    private var cacheKey: String? {
        if case .memory(let key) = cachePolicy { return key }
        return nil
    }

    private func load() {
        loadTask?.cancel()
        guard let url else {
            phase = .failure
            return
        }

        if let cacheKey, let cached = ImageMemoryCache.image(for: cacheKey) {
            phase = .success(Image(uiImage: cached))
            return
        }

        phase = .loading
        let targetSize = self.targetSize
        let cacheKey = self.cacheKey
        loadTask = Task {
            var request = URLRequest(url: url)
            request.timeoutInterval = timeout
            do {
                let (data, response) = try await URLSession.shared.data(for: request)
                guard !Task.isCancelled else { return }
                guard let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode),
                      let uiImage = Self.decode(data, targetSize: targetSize) else {
                    phase = .failure
                    return
                }
                if let cacheKey {
                    ImageMemoryCache.store(uiImage, for: cacheKey)
                }
                phase = .success(Image(uiImage: uiImage))
            } catch {
                guard !Task.isCancelled else { return }
                phase = .failure
            }
        }
    }

    /// Downsamples at decode time via ImageIO's thumbnail generator rather than decoding full
    /// resolution then resizing — the whole point is to never allocate the full-size bitmap in
    /// the first place. Falls back to a plain full-resolution decode when no target size was
    /// given (existing behavior, unchanged).
    private static func decode(_ data: Data, targetSize: CGSize?) -> UIImage? {
        guard let targetSize, targetSize.width > 0, targetSize.height > 0 else {
            return UIImage(data: data)
        }

        guard let source = CGImageSourceCreateWithData(data as CFData, nil) else { return nil }

        let scale = UIScreen.main.scale
        let maxPixelSize = max(targetSize.width, targetSize.height) * scale
        let options: [CFString: Any] = [
            kCGImageSourceCreateThumbnailFromImageAlways: true,
            kCGImageSourceThumbnailMaxPixelSize: maxPixelSize,
            kCGImageSourceCreateThumbnailWithTransform: true,
        ]

        guard let cgImage = CGImageSourceCreateThumbnailAtIndex(source, 0, options as CFDictionary) else {
            return UIImage(data: data)
        }
        return UIImage(cgImage: cgImage, scale: scale, orientation: .up)
    }
}
