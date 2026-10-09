import SwiftUI

struct AmbassadorShareSheet: View {
    let role: AmbassadorRole

    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var format: AmbassadorCardFormat = .story
    @State private var rendered: Image?
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
                ShareLink(
                    item: rendered,
                    message: Text(Copy.ambassadorShareMessage(community: role.name)),
                    preview: SharePreview(Copy.ambassadorShareCardLabel, image: rendered)
                ) {
                    Label(Copy.ambassadorShareButton, systemImage: "square.and.arrow.up")
                        .font(.headline)
                        .fixedSize(horizontal: true, vertical: false)
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity, minHeight: 56)
                        .padding(.horizontal, 16)
                        .background(Color.sambalRed, in: Capsule())
                }
                .buttonStyle(PressCompressStyle())
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
        rendered = renderer.uiImage.map { Image(uiImage: $0) }
    }
}
