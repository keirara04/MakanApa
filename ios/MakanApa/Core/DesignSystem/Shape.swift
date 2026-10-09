import SwiftUI

/// The corner scale. Cards are the big tappable blocks (Home's entry points), rows are list
/// items inside a section, icon badges are circles, CTAs are capsules. Always `.continuous`.
enum Radius {
    static let card: CGFloat = 24
    static let row: CGFloat = 16
}

extension Shape where Self == RoundedRectangle {
    static var card: RoundedRectangle { RoundedRectangle(cornerRadius: Radius.card, style: .continuous) }
    static var row: RoundedRectangle { RoundedRectangle(cornerRadius: Radius.row, style: .continuous) }
}
