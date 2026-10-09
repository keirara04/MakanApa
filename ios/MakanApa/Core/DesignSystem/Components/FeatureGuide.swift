import SwiftUI

// Picture guides ("How Community works", "How Decide works", "How Nearby works"): a thin card at
// the end of a screen opens a swipeable sheet, one feature per page. Each page draws the real
// control in miniature — grey bars stand in for text, only the control being taught carries its
// real label — rings it with a soft pulse and a tapping hand, and explains it in a line or two.

/// The thin, full-width row used for low-key actions at the end of a screen: an icon, a title,
/// one line of detail, and a chevron.
struct CompactActionCard: View {
    let systemImage: String
    let title: String
    let detail: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                Image(systemName: systemImage)
                    .font(.title3)
                    .foregroundStyle(Color.sambalRed)
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 1) {
                    Text(title)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(Color.kicap)
                    Text(detail)
                        .font(.footnote)
                        .foregroundStyle(Color.kicapSecondary)
                        .fixedSize(horizontal: false, vertical: true)
                }
                Spacer(minLength: 0)
                Image(systemName: "chevron.right")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Color.kicapSecondary)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .frame(maxWidth: .infinity, minHeight: 52, alignment: .leading)
            .background(Color.surface, in: .row)
            .overlay(RoundedRectangle.row.strokeBorder(Color.hairline, lineWidth: 1))
        }
        .buttonStyle(PressCompressStyle())
    }
}

struct FeatureGuidePage {
    let title: String
    let body: String
}

/// The swipeable guide itself. `art` draws page `index`'s illustration.
struct FeatureGuideSheet<Art: View>: View {
    let title: String
    let pages: [FeatureGuidePage]
    @ViewBuilder let art: (Int) -> Art

    @Environment(\.dismiss) private var dismiss
    @State private var page = 0

    var body: some View {
        NavigationStack {
            VStack(spacing: 16) {
                TabView(selection: $page) {
                    ForEach(pages.indices, id: \.self) { index in
                        pageView(index)
                            .tag(index)
                    }
                }
                .tabViewStyle(.page(indexDisplayMode: .always))
                .indexViewStyle(.page(backgroundDisplayMode: .always))

                Button {
                    if page == pages.count - 1 {
                        dismiss()
                    } else {
                        withAnimation(Motion.standard) { page += 1 }
                    }
                } label: {
                    Text(page == pages.count - 1 ? Copy.guideDone : Copy.guideNext)
                        .font(.makanBody(16).weight(.semibold))
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity, minHeight: 52)
                        .background(Color.sambalRed, in: Capsule())
                }
                .buttonStyle(PressCompressStyle())
                .padding(.horizontal, 20)
                .padding(.bottom, 8)
            }
            .background(Color.nasiCream.ignoresSafeArea())
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(Copy.close) { dismiss() }
                }
            }
            .sensoryFeedback(.selection, trigger: page)
        }
    }

    private func pageView(_ index: Int) -> some View {
        let page = pages[index]
        return ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                GuideArtFrame { art(index) }
                Text(page.title)
                    .font(.makanDisplay(22))
                    .foregroundStyle(Color.kicap)
                Text(page.body)
                    .font(.makanBody(16))
                    .foregroundStyle(Color.kicapSecondary)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .padding(.horizontal, 20)
            .padding(.top, 8)
            .padding(.bottom, 48)
        }
        .scrollBounceBehavior(.basedOnSize)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(String(format: Copy.guidePageFormat, index + 1, pages.count)). \(page.title). \(page.body)")
    }
}

// MARK: - Drawing pieces

/// The card every illustration sits on.
struct GuideArtFrame<Content: View>: View {
    @ViewBuilder var content: () -> Content

    var body: some View {
        ZStack { content() }
            .frame(maxWidth: .infinity)
            .frame(height: 240)
            .background(Color.surface, in: .card)
            .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
            .clipShape(.card)
            .accessibilityHidden(true)
    }
}

/// A grey bar standing in for a line of text.
struct GuideBar: View {
    var width: CGFloat
    var height: CGFloat = 9

    var body: some View {
        Capsule().fill(Color.hairline).frame(width: width, height: height)
    }
}

/// A small floating menu or list panel.
struct GuideMenu<Content: View>: View {
    var width: CGFloat = 210
    @ViewBuilder var content: () -> Content

    var body: some View {
        VStack(spacing: 0) { content() }
            .padding(6)
            .frame(width: width)
            .background(Color.nasiCream, in: .row)
            .overlay(RoundedRectangle.row.strokeBorder(Color.hairline, lineWidth: 1))
            .shadow(color: .black.opacity(0.08), radius: 10, y: 6)
    }
}

struct GuideMenuRow<RowLabel: View>: View {
    let systemImage: String
    var highlighted = false
    @ViewBuilder var label: () -> RowLabel

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: systemImage)
                .frame(width: 20)
                .foregroundStyle(highlighted ? Color.sambalRed : Color.kicapSecondary)
            label()
                .font(.makanBody(14))
                .foregroundStyle(Color.kicap)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 10)
        .frame(height: 38)
        .background(highlighted ? Color.sambalRed.opacity(0.1) : .clear, in: RoundedRectangle(cornerRadius: 10, style: .continuous))
    }
}

/// A capsule chip, filled when it's the "on" state.
struct GuideChip: View {
    let text: String
    var isOn = false

    var body: some View {
        Text(text)
            .font(.makanBody(12).weight(.semibold))
            .foregroundStyle(isOn ? .white : Color.kicap)
            .padding(.horizontal, 12)
            .frame(height: 30)
            .background(isOn ? Color.sambalRed : Color.nasiCream, in: Capsule())
            .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: isOn ? 0 : 1))
    }
}

/// Rings the control to tap with a soft pulse and puts a tapping hand beside it.
struct GuideTapHint: ViewModifier {
    var cornerRadius: CGFloat = 16
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var pulse = false

    func body(content: Content) -> some View {
        content
            .overlay {
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .strokeBorder(Color.sambalRed, lineWidth: 2)
                    .padding(-6)
                    .scaleEffect(pulse ? 1.06 : 1)
                    .opacity(pulse ? 0.4 : 1)
            }
            .overlay(alignment: .bottomTrailing) {
                Image(systemName: "hand.tap.fill")
                    .font(.title2)
                    .foregroundStyle(Color.kicap)
                    .shadow(color: .white, radius: 2)
                    .offset(x: 16, y: 20)
            }
            .onAppear {
                guard !reduceMotion else { return }
                withAnimation(.easeInOut(duration: 0.9).repeatForever(autoreverses: true)) { pulse = true }
            }
    }
}

extension View {
    func guideTapHint(cornerRadius: CGFloat = 16) -> some View {
        modifier(GuideTapHint(cornerRadius: cornerRadius))
    }
}
