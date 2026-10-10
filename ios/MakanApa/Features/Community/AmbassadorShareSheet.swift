import LinkPresentation
import SwiftUI

struct AmbassadorShareSheet: View {
    let role: AmbassadorRole

    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var format: AmbassadorCardFormat = .story
    @State private var rendered: UIImage?
    @State private var sharing = false
    @State private var shown = false

    private var name: String {
        if case .authenticated(let user) = AuthStore.shared.session, let name = user.name, !name.isEmpty { return name }
        return Copy.ambassadorShareCardFallbackName
    }

    var body: some View {
        NavigationStack {
            GeometryReader { geometry in
                let previewWidth = min(geometry.size.width - 48, 360, max(220, (geometry.size.height - 90) * 360 / format.height))
                ScrollView {
                    VStack(spacing: 20) {
                        // Keep the entire export visible when there is room, with a scroll fallback.
                        AmbassadorShareCard(role: role, name: name, format: format)
                            .clipShape(.rect(cornerRadius: 20))
                            .scaleEffect(previewWidth / format.size.width, anchor: .topLeading)
                            .frame(width: previewWidth, height: previewWidth * format.height / format.size.width, alignment: .topLeading)
                            .shadow(color: Color.kicap.opacity(0.12), radius: 16, y: 8)
                            .scaleEffect(shown || reduceMotion ? 1 : 0.96)
                            .opacity(shown ? 1 : 0)
                            .animation(reduceMotion ? .easeOut(duration: 0.2) : Motion.playful, value: shown)
                            .accessibilityElement(children: .ignore)
                            .accessibilityLabel(Copy.ambassadorShareCardAccessibility(name: name, community: role.name))

                        Text(Copy.ambassadorShareHint)
                            .font(.subheadline)
                            .foregroundStyle(Color.kicapSecondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 24)
                    }
                    .frame(maxWidth: 460)
                    .frame(maxWidth: .infinity)
                    .padding(.top, 12)
                    .padding(.bottom, 24)
                }
            }
            .safeAreaInset(edge: .top, spacing: 0) {
                Picker(Copy.ambassadorShareFormatLabel, selection: $format) {
                    ForEach(AmbassadorCardFormat.allCases) { format in
                        Text(format.title).tag(format)
                    }
                }
                .pickerStyle(.segmented)
                .frame(maxWidth: 460)
                .padding(.horizontal, 24)
                .padding(.vertical, 12)
                .frame(maxWidth: .infinity)
                .background(Color.nasiCream)
            }
            .safeAreaInset(edge: .bottom) {
                shareCardButton
                .frame(maxWidth: 460)
                .padding(.horizontal, 20)
                .padding(.vertical, 12)
                .background(Color.nasiCream)
            }
            .background(Color.nasiCream.ignoresSafeArea())
            .navigationTitle(Copy.ambassadorShareCardLabel)
            .navigationBarTitleDisplayMode(.inline)
            .toolbarBackground(Color.nasiCream, for: .navigationBar)
            .toolbarBackground(.visible, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button(Copy.ambassadorShareDone, action: { dismiss() })
                        .bold()
                }
            }
        }
        .onAppear { shown = true }
        .task(id: format) { renderCard() }
    }

    private var shareCardButton: some View {
        Group {
            if let rendered {
                // UIKit's share sheet with a real UIImage, not ShareLink: ShareLink hands apps a
                // lazily exported SwiftUI Image plus the caption, which Instagram's share
                // extension fails to load ("an error occurred") until the card is saved first.
                Button { sharing = true } label: {
                    Label(Copy.ambassadorShareButton, systemImage: "square.and.arrow.up")
                        .font(.headline)
                        .fixedSize(horizontal: true, vertical: false)
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity, minHeight: 56)
                        .padding(.horizontal, 16)
                        .background(Color.sambalRed, in: Capsule())
                }
                .buttonStyle(PressCompressStyle())
                .sheet(isPresented: $sharing) {
                    ActivityShareSheet(items: [
                        ShareCardImageItem(image: rendered, title: Copy.ambassadorShareCardLabel),
                        ShareCaptionItem(text: Copy.ambassadorShareMessage(community: role.name)),
                    ]) { sharing = false }
                    .presentationDetents([.medium, .large])
                    .ignoresSafeArea()
                }
            } else {
                Button(Copy.ambassadorShareRetry, systemImage: "arrow.clockwise", action: renderCard)
                    .font(.headline)
                    .frame(maxWidth: .infinity, minHeight: 56)
            }
        }
    }

    /// Both canvases are 360pt wide: 3× exports 1080 × 1920 or 1080 × 1350.
    private func renderCard() {
        rendered = nil
        let renderer = ImageRenderer(content: AmbassadorShareCard(role: role, name: name, format: format))
        renderer.scale = 3
        renderer.isOpaque = true
        rendered = renderer.uiImage
    }
}

/// UIKit's share sheet, for sharing a finished image the way every share extension expects.
private struct ActivityShareSheet: UIViewControllerRepresentable {
    let items: [Any]
    let onFinish: () -> Void

    func makeUIViewController(context: Context) -> UIActivityViewController {
        let controller = UIActivityViewController(activityItems: items, applicationActivities: nil)
        controller.completionWithItemsHandler = { _, _, _, _ in onFinish() }
        return controller
    }

    func updateUIViewController(_ controller: UIActivityViewController, context: Context) {}
}

/// The card itself, with a titled preview at the top of the share sheet.
private final class ShareCardImageItem: NSObject, UIActivityItemSource {
    let image: UIImage
    let title: String

    init(image: UIImage, title: String) {
        self.image = image
        self.title = title
    }

    func activityViewControllerPlaceholderItem(_ controller: UIActivityViewController) -> Any { image }

    func activityViewController(_ controller: UIActivityViewController, itemForActivityType activityType: UIActivity.ActivityType?) -> Any? { image }

    func activityViewControllerLinkMetadata(_ controller: UIActivityViewController) -> LPLinkMetadata? {
        let metadata = LPLinkMetadata()
        metadata.title = title
        metadata.imageProvider = NSItemProvider(object: image)
        return metadata
    }
}

/// The caption, left out where it would break or be dropped: Instagram's extension rejects an
/// image that arrives with text, and Photos has nowhere to put it.
private final class ShareCaptionItem: NSObject, UIActivityItemSource {
    let text: String

    init(text: String) {
        self.text = text
    }

    func activityViewControllerPlaceholderItem(_ controller: UIActivityViewController) -> Any { text }

    func activityViewController(_ controller: UIActivityViewController, itemForActivityType activityType: UIActivity.ActivityType?) -> Any? {
        if activityType == .saveToCameraRoll || activityType?.rawValue.localizedCaseInsensitiveContains("instagram") == true {
            return nil
        }
        return text
    }
}
