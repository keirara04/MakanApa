import SwiftUI

enum AmbassadorCardFormat: String, CaseIterable, Identifiable {
    case story
    case post

    var id: Self { self }
    var title: String { self == .story ? Copy.ambassadorShareStory : Copy.ambassadorSharePost }
    var height: CGFloat { self == .story ? 640 : 450 }
    var size: CGSize { CGSize(width: 360, height: height) }
}
