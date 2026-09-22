import UIKit

/// RAM-only, keyed by URL — never written to disk, cleared on relaunch. Not "persisting" Places
/// photo content in the sense Google's terms restrict (long-term/disk storage); this just avoids
/// re-fetching (and re-billing) the same photo twice within one app session.
enum ImageMemoryCache {
    // NSCache is documented thread-safe internally — the compiler just can't see that, since
    // it's an Obj-C type predating Sendable.
    nonisolated(unsafe) private static let cache: NSCache<NSString, UIImage> = {
        let cache = NSCache<NSString, UIImage>()
        cache.countLimit = 200
        return cache
    }()

    static func image(for url: URL) -> UIImage? {
        cache.object(forKey: url.absoluteString as NSString)
    }

    static func store(_ image: UIImage, for url: URL) {
        cache.setObject(image, forKey: url.absoluteString as NSString)
    }
}
