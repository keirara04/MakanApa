import SwiftUI

/// Full-screen photo browser for a place — opens on a tappable grid (the "library"), and
/// tapping any thumbnail jumps into a swipeable full-screen viewer at that exact photo.
struct PhotoGalleryView: View {
    let urls: [URL]
    /// Google photo credits, parallel to `urls`. Shorter than `urls` when the tail is community
    /// photos, which carry no credit.
    var credits: [[RecommendationResponse.PhotoAttribution]] = []
    @Environment(\.dismiss) private var dismiss
    @State private var selectedIndex: Int?

    private static let columns = [GridItem(.flexible(), spacing: 2), GridItem(.flexible(), spacing: 2), GridItem(.flexible(), spacing: 2)]

    var body: some View {
        ZStack(alignment: .top) {
            Color.black.ignoresSafeArea()

            if let selectedIndex {
                viewer(startingAt: selectedIndex)
            } else {
                grid
            }
        }
    }

    // MARK: - Library grid

    private var grid: some View {
        ScrollView {
            LazyVGrid(columns: Self.columns, spacing: 2) {
                ForEach(Array(urls.enumerated()), id: \.offset) { index, url in
                    Button {
                        selectedIndex = index
                    } label: {
                        RemoteImage(url: url) {
                            Color.white.opacity(0.08)
                        }
                        .aspectRatio(1, contentMode: .fill)
                        .clipped()
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.top, 56)
        }
        .overlay(alignment: .top) {
            galleryHeader(title: "\(urls.count) photo\(urls.count == 1 ? "" : "s")") {
                dismiss()
            }
        }
    }

    // MARK: - Full-screen viewer

    @ViewBuilder
    private func viewer(startingAt index: Int) -> some View {
        ViewerPager(urls: urls, page: Binding(
            get: { selectedIndex ?? index },
            set: { selectedIndex = $0 }
        ))
        .overlay(alignment: .bottomLeading) {
            let current = selectedIndex ?? index
            if credits.indices.contains(current) {
                GooglePhotoCredit(attributions: credits[current])
                    .padding(.bottom, 24)
            }
        }
        .overlay(alignment: .top) {
            galleryHeader(
                title: urls.count > 1 ? "\((selectedIndex ?? index) + 1)/\(urls.count)" : nil,
                backIcon: "chevron.left"
            ) {
                selectedIndex = nil
            }
        }
    }

    private func galleryHeader(title: String?, backIcon: String = "xmark", action: @escaping () -> Void) -> some View {
        HStack {
            Button(action: action) {
                Image(systemName: backIcon)
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundStyle(.white)
                    .padding(10)
                    .background(.black.opacity(0.5))
                    .clipShape(Circle())
            }
            Spacer()
            if let title {
                Text(title)
                    .font(.makanBody(12))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(.black.opacity(0.5))
                    .clipShape(Capsule())
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }
}

/// Just the swipeable `TabView` — split out so `PhotoGalleryView.viewer` can bind its page to
/// `selectedIndex` directly instead of juggling a second piece of state to stay in sync.
private struct ViewerPager: View {
    let urls: [URL]
    @Binding var page: Int

    var body: some View {
        TabView(selection: $page) {
            ForEach(Array(urls.enumerated()), id: \.offset) { index, url in
                RemoteImage(url: url) {
                    Color.black
                }
                .aspectRatio(contentMode: .fit)
                .tag(index)
            }
        }
        .tabViewStyle(.page(indexDisplayMode: .never))
    }
}

/// Google requires each Places photo to credit its author wherever it's shown: the name, linked
/// to their profile when Google gives one. Renders nothing for uncredited photos.
struct GooglePhotoCredit: View {
    let attributions: [RecommendationResponse.PhotoAttribution]

    var body: some View {
        let names = attributions.compactMap(\.name).filter { !$0.isEmpty }
        if !names.isEmpty {
            let label = Text("Photo: \(names.joined(separator: ", "))")
            Group {
                if let profile = attributions.first(where: { $0.profileUrl != nil })?.profileUrl.flatMap(URL.init(string:)) {
                    Link(destination: profile) { label.underline() }
                } else {
                    label
                }
            }
            .font(.caption2)
            .foregroundStyle(.white)
            .lineLimit(1)
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(.black.opacity(0.5), in: Capsule())
            .padding(10)
        }
    }
}
