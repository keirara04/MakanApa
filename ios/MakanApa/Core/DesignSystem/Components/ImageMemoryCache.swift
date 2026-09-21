import UIKit

/// Process-memory only, never disk-backed — deliberately not an HTTP/URLCache change. Google
/// Places photos must never be cached here (see `RemoteImage.ImageCachePolicy`); this exists so
/// MakanApa's own community/S3 restaurant photos don't get redundantly re-fetched and re-decoded
/// when they scroll back into view within a session. NSCache auto-evicts under memory pressure
/// and is gone on relaunch.
enum ImageMemoryCache {
    private static let cache: NSCache<NSString, UIImage> = {
        let cache = NSCache<NSString, UIImage>()
        // Cost-based, not just count-based — a handful of full-size photos and 200 small
        // thumbnails are very different memory footprints. Tune after an Instruments pass.
        cache.totalCostLimit = 64 * 1024 * 1024
        return cache
    }()

    static func image(for key: String) -> UIImage? {
        cache.object(forKey: key as NSString)
    }

    static func store(_ image: UIImage, for key: String) {
        let cost = image.cgImage.map { $0.bytesPerRow * $0.height } ?? 0
        cache.setObject(image, forKey: key as NSString, cost: cost)
    }
}
