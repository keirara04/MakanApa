import UIKit

extension UIImage {
    func resizedIfNeeded(maxDimension: CGFloat) -> UIImage {
        let scale = min(1.0, maxDimension / max(size.width, size.height))
        guard scale < 1.0 else { return self }

        let newSize = CGSize(width: size.width * scale, height: size.height * scale)
        let renderer = UIGraphicsImageRenderer(size: newSize)
        return renderer.image { _ in draw(in: CGRect(origin: .zero, size: newSize)) }
    }
}
