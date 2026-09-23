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
        cache.object(forKey: key(for: url))
    }

    static func store(_ image: UIImage, for url: URL) {
        cache.setObject(image, forKey: key(for: url))
    }

    /// The backend's photo proxy URLs are freshly signed (new `expires`/`signature`) on every
    /// response, so the same Google photo arrives under a different URL after each reroll or
    /// reopen. Keying those on the stable `name` parameter lets the second view hit memory.
    private static func key(for url: URL) -> NSString {
        if url.path.hasSuffix("/places/photo"),
           let name = URLComponents(url: url, resolvingAgainstBaseURL: false)?
               .queryItems?.first(where: { $0.name == "name" })?.value {
            return "places-photo:\(name)" as NSString
        }
        return url.absoluteString as NSString
    }
}
