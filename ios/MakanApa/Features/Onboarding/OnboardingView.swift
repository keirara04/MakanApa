import SwiftUI
import UIKit

/// Runs once, before the auth gate (see MakanApaApp.swift), so a first-time opener sees the
/// value prop and gives location before anything ever asks for an account. No auth language
/// anywhere in here on purpose — "you're already in" is the whole point of this screen existing.
struct OnboardingView: View {
    let onFinished: () -> Void

    @State private var page = 0
    /// Which way the last move went, so Back slides the other way. Set a beat before `page` —
    /// the outgoing page only picks up a new transition if it re-renders with it first.
    @State private var isMovingForward = true
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private let pageCount = 4

    var body: some View {
        VStack(spacing: 0) {
            topBar
                .padding(.top, 8)

            ZStack {
                switch page {
                case 0:
                    OnboardingValuePropPage(onContinue: { advance(from: 0) })
                        .transition(pageTransition)
                case 1:
                    OnboardingLocationPage(onContinue: { advance(from: 1) })
                        .transition(pageTransition)
                case 2:
                    OnboardingHalalPage(onContinue: { advance(from: 2) })
                        .transition(pageTransition)
                default:
                    OnboardingTastePage(onFinish: finish)
                        .transition(pageTransition)
                }
            }
            .animation(reduceMotion ? .easeInOut(duration: 0.18) : Motion.standard, value: page)
            .frame(maxHeight: .infinity)
        }
        .background(Color.nasiCream.ignoresSafeArea())
    }

    private var pageTransition: AnyTransition {
        if reduceMotion {
            return .opacity
        }
        let shift: CGFloat = isMovingForward ? 24 : -24
        return .asymmetric(
            insertion: .opacity.combined(with: .offset(x: shift)),
            removal: .opacity.combined(with: .offset(x: -shift))
        )
    }

    /// Back on the left, progress in the middle. An empty slot of the same width on the right
    /// keeps the dots centred whether or not Back is showing.
    private var topBar: some View {
        HStack {
            Button {
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
                go(to: page - 1)
            } label: {
                Image(systemName: "chevron.left")
                    .font(.system(size: 17, weight: .semibold))
                    .foregroundStyle(Color.kicap)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .opacity(page > 0 ? 1 : 0)
            .disabled(page == 0)
            .accessibilityLabel("Back")
            .accessibilityHidden(page == 0)

            Spacer()
            progressDots
            Spacer()

            Color.clear.frame(width: 44, height: 44)
        }
        .padding(.horizontal, 12)
    }

    private var progressDots: some View {
        HStack(spacing: 6) {
            ForEach(0..<pageCount, id: \.self) { index in
                Capsule()
                    .fill(index <= page ? Color.sambalRed : Color.kicap.opacity(0.15))
                    .frame(width: index == page ? 18 : 6, height: 6)
                    .animation(reduceMotion ? .easeOut(duration: 0.15) : Motion.quick, value: page)
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Step \(page + 1) of \(pageCount)")
    }

    /// Only moves on from the page that asked — a late or repeated callback (two location
    /// updates in a row, a double tap) can't skip the page after it.
    private func advance(from expectedPage: Int) {
        guard page == expectedPage else { return }
        go(to: page + 1)
    }

    private func go(to newPage: Int) {
        let target = min(max(newPage, 0), pageCount - 1)
        guard target != page else { return }
        isMovingForward = target > page
        Task { @MainActor in
            page = target
            // VoiceOver: start reading the new page from the top.
            UIAccessibility.post(notification: .screenChanged, argument: nil)
        }
    }

    private func finish() {
        OnboardingState.shared.complete()
        onFinished()
    }
}

// MARK: - Pages

private struct OnboardingValuePropPage: View {
    let onContinue: () -> Void

    var body: some View {
        OnboardingPageLayout(
            content: {
                ZStack {
                    ForEach(Array(floatingLabels.enumerated()), id: \.offset) { index, label in
                        FloatingLabelChip(text: label, index: index)
                            .offset(floatingOffset(for: index))
                    }
                    MascotView(mood: .idle, size: 140)
                }
                .frame(height: 220)
                .accessibilityHidden(true)
            },
            headline: "What should we makan? 👀",
            subtext: "Discover places nearby, hidden gems, and spots your community is actually picking.",
            primaryTitle: "Jom explore",
            primaryAction: onContinue
        )
    }

    private let floatingLabels = ["Under RM10", "Late night", "Cafe", "Community find"]

    private func floatingOffset(for index: Int) -> CGSize {
        switch index {
        case 0: return CGSize(width: -105, height: -70)
        case 1: return CGSize(width: 100, height: -50)
        case 2: return CGSize(width: -100, height: 60)
        default: return CGSize(width: 100, height: 75)
        }
    }
}

/// Decorative tag around the mascot — drifts gently, each on its own rhythm so they never move
/// in lockstep. Still under Reduce Motion.
private struct FloatingLabelChip: View {
    let text: String
    let index: Int

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var isUp = false

    var body: some View {
        Text(text)
            .font(.makanBody(12))
            .foregroundStyle(Color.kicap.opacity(0.8))
            .padding(.horizontal, 10)
            .padding(.vertical, 6)
            .background(Color.white, in: Capsule())
            .shadow(color: Color.kicap.opacity(0.08), radius: 4, y: 2)
            .offset(y: isUp ? -4 : 4)
            .animation(
                reduceMotion ? nil : .easeInOut(duration: 2.2 + Double(index) * 0.35).repeatForever(autoreverses: true),
                value: isUp
            )
            .onAppear {
                guard !reduceMotion else { return }
                isUp = true
            }
    }
}

private struct OnboardingLocationPage: View {
    let onContinue: () -> Void

    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var didRequest = false
    @State private var didFinish = false
    @State private var showConfirmation = false

    var body: some View {
        OnboardingPageLayout(
            content: {
                ZStack {
                    Image("LocationMap")
                        .resizable()
                        .scaledToFit()
                        .opacity(0.18)
                    Image("MascotLocation")
                        .resizable()
                        .scaledToFit()
                }
                .frame(width: 150, height: 150)
                .accessibilityHidden(true)
                // Overlaid, not stacked, so the confirmation never pushes the headline down.
                .overlay(alignment: .bottom) {
                    if showConfirmation {
                        Label("Nice, location's on", systemImage: "checkmark.circle.fill")
                            .font(.makanBody(13).weight(.semibold))
                            .foregroundStyle(Color.pandan)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 6)
                            .background(Color.white, in: Capsule())
                            .fixedSize()
                            .offset(y: 18)
                            .transition(.opacity.combined(with: .scale(scale: 0.9)))
                    }
                }
            },
            headline: "Find good food around you 📍",
            subtext: "We use your location to show places nearby, what's trending around you, and better \"Pick one lah\" results.",
            footnote: OnboardingFootnote(icon: "lock.fill", text: "Your location is never shown to other people."),
            // App Review (guideline 5.1.1(iv)): a pre-permission screen must use neutral wording
            // and always lead to the system prompt — no "Maybe later" to dodge it. The system
            // dialog itself is where the user says no.
            primaryTitle: "Continue",
            primaryAction: requestLocation,
            // Waiting on the system prompt — a second tap would only re-ask.
            isPrimaryEnabled: !didRequest || didFinish
        )
        // The permission answer, not the first GPS fix — that can take seconds after "Allow",
        // which used to leave this screen looking frozen.
        .onChange(of: locationService.authorization) { _, status in
            guard didRequest else { return }
            switch status {
            case .authorizedWhenInUse, .authorizedAlways: confirmAndContinue()
            case .denied, .restricted: finish()
            default: break
            }
        }
        // Debug location override and a failed fix report through `state` instead.
        .onChange(of: locationService.state) { _, state in
            guard didRequest else { return }
            switch state {
            case .authorized: confirmAndContinue()
            case .denied, .unavailable: finish()
            case .notDetermined: break
            }
        }
    }

    private func requestLocation() {
        didRequest = true

        // Decided in an earlier session: the system won't prompt again and nothing will change,
        // so waiting on onChange would leave this screen stuck.
        switch locationService.authorization {
        case .authorizedWhenInUse, .authorizedAlways:
            confirmAndContinue()
            return
        case .denied, .restricted:
            finish()
            return
        default:
            break
        }
        if case .authorized = locationService.state {
            confirmAndContinue()
            return
        }

        locationService.requestLocation()
    }

    private func confirmAndContinue() {
        guard !didFinish else { return }
        didFinish = true
        UINotificationFeedbackGenerator().notificationOccurred(.success)
        withAnimation(reduceMotion ? .easeOut(duration: 0.15) : Motion.playful) { showConfirmation = true }
        Task {
            try? await Task.sleep(for: .milliseconds(700))
            onContinue()
        }
    }

    private func finish() {
        guard !didFinish else { return }
        didFinish = true
        onContinue()
    }
}

/// Asked once, up front — a dietary requirement shouldn't be buried in Settings. Changeable
/// anytime with the "Hide non-halal" chip on the Nearby map.
private struct OnboardingHalalPage: View {
    let onContinue: () -> Void

    var body: some View {
        OnboardingPageLayout(
            content: {
                Image(systemName: "checkmark.seal.fill")
                    .font(.system(size: 64))
                    .foregroundStyle(Color.pandan)
                    .frame(width: 132, height: 132)
                    .background(Color.pandan.opacity(0.12), in: Circle())
                    .frame(height: 150)
                    .accessibilityHidden(true)
            },
            headline: "Do you only eat halal?",
            subtext: "We'll hide places known to be non-halal. Places we haven't verified yet still show, clearly marked. Change it anytime from the map.",
            footnote: OnboardingFootnote(icon: "info.circle.fill", text: "Halal info comes from the community. Always double-check at the restaurant."),
            primaryTitle: "Yes, hide non-halal",
            primaryAction: {
                HalalPreference.isOn = true
                onContinue()
            },
            secondaryTitle: "No, show everything",
            secondaryAction: {
                HalalPreference.isOn = false
                onContinue()
            }
        )
    }
}

private struct OnboardingTastePage: View {
    let onFinish: () -> Void

    @State private var state = OnboardingState.shared
    /// Bumped when a tap is refused at the limit — shakes the counter so the limit is seen.
    @State private var limitNudge = 0
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private let cuisines = ["Mamak", "Cafe", "Korean", "Malay", "Dessert", "Cheap eats", "Late night", OnboardingState.anythingPick]
    private let columns = [GridItem(.adaptive(minimum: 100), spacing: 10)]
    private let maxPicks = OnboardingState.maxPicks

    private var isFull: Bool { state.selectedCuisines.count >= maxPicks }

    var body: some View {
        OnboardingPageLayout(
            content: {
                VStack(spacing: 12) {
                    LazyVGrid(columns: columns, spacing: 10) {
                        ForEach(cuisines, id: \.self) { cuisine in
                            cuisineChip(cuisine)
                        }
                    }
                    Text(counterText)
                        .font(.makanBody(12).weight(.semibold))
                        .foregroundStyle(isFull ? Color.sambalRed : Color.kicap.opacity(0.6))
                        .contentTransition(.numericText())
                        .modifier(ShakeEffect(trigger: reduceMotion ? 0 : limitNudge))
                        .animation(.easeOut(duration: 0.35), value: limitNudge)
                        .animation(Motion.quick, value: state.selectedCuisines.count)
                }
                .padding(.horizontal, 8)
            },
            headline: "What are you usually craving?",
            subtext: "Pick up to \(maxPicks). They shape your first few picks.",
            primaryTitle: "Start exploring",
            primaryAction: onFinish,
            // Nothing picked is what "Skip for now" is for — two buttons doing the same thing
            // was just confusing.
            isPrimaryEnabled: !state.selectedCuisines.isEmpty,
            secondaryTitle: "Skip for now",
            secondaryAction: onFinish
        )
    }

    private var counterText: String {
        let count = state.selectedCuisines.count
        return count == 0 ? "None picked yet" : "\(count) of \(maxPicks) picked"
    }

    private func cuisineChip(_ cuisine: String) -> some View {
        let isSelected = state.selectedCuisines.contains(cuisine)
        // "Anything lah" replaces the others instead of adding to them, so it's never locked.
        let isLocked = isFull && !isSelected && cuisine != OnboardingState.anythingPick

        return Button {
            if isLocked {
                UINotificationFeedbackGenerator().notificationOccurred(.warning)
                limitNudge += 1
                return
            }
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            withAnimation(reduceMotion ? .easeOut(duration: 0.12) : Motion.playful) {
                state.toggleCuisine(cuisine)
            }
        } label: {
            HStack(spacing: 5) {
                if isSelected {
                    Image(systemName: "checkmark")
                        .font(.system(size: 11, weight: .bold))
                        .transition(.scale.combined(with: .opacity))
                }
                Text(cuisine)
                    .lineLimit(1)
                    .minimumScaleFactor(0.85)
            }
            .font(.makanBody(14))
            .foregroundStyle(isSelected ? .white : Color.kicap)
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .frame(maxWidth: .infinity)
            .background(isSelected ? Color.sambalRed : Color.white, in: Capsule())
            .opacity(isLocked ? 0.45 : 1)
        }
        .buttonStyle(PressCompressStyle())
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityHint(isLocked ? "You've picked \(maxPicks). Unpick one first." : "")
    }
}

/// Hori - Layout

/// Small icon + note under the subtext — the privacy promise / safety caveat, kept visually
/// separate so the main explanation stays short.
private struct OnboardingFootnote {
    let icon: String
    let text: String
}

/// Shared skeleton: illustration up top, headline + subtext in the middle, CTA + secondary
/// pinned to the bottom. Every onboarding page fits this shape, so the layout lives once here
/// instead of being re-typed per page.
///
/// Colours are all explicit brand colours on purpose — the cream background never changes, so
/// adaptive system colours (`.secondary`) would turn light-on-cream for anyone in Dark Mode.
private struct OnboardingPageLayout<Content: View>: View {
    @ViewBuilder let content: () -> Content
    let headline: String
    let subtext: String
    var footnote: OnboardingFootnote? = nil
    let primaryTitle: String
    let primaryAction: () -> Void
    var isPrimaryEnabled = true
    var secondaryTitle: String? = nil
    var secondaryAction: (() -> Void)? = nil

    var body: some View {
        // Scrolls only when it has to (small phones, large text) — otherwise centred as before,
        // and the buttons stay pinned either way.
        GeometryReader { proxy in
            ScrollView {
                VStack(spacing: 28) {
                    Spacer(minLength: 0)
                    content()
                    copy
                    Spacer(minLength: 0)
                }
                .frame(maxWidth: .infinity, minHeight: proxy.size.height)
            }
            .scrollBounceBehavior(.basedOnSize)
            .scrollIndicators(.hidden)
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            buttons
        }
    }

    private var copy: some View {
        VStack(spacing: 10) {
            Text(headline)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
                .accessibilityAddTraits(.isHeader)

            Text(subtext)
                .font(.makanBody(15))
                .foregroundStyle(Color.kicap.opacity(0.72))
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)

            if let footnote {
                Label(footnote.text, systemImage: footnote.icon)
                    .font(.makanBody(12))
                    .foregroundStyle(Color.kicap.opacity(0.6))
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                    .padding(.top, 4)
            }
        }
        .padding(.horizontal, 32)
    }

    private var buttons: some View {
        VStack(spacing: 6) {
            MakanPrimaryButton(title: primaryTitle, action: primaryAction)
                .disabled(!isPrimaryEnabled)
                .opacity(isPrimaryEnabled ? 1 : 0.45)
                .animation(Motion.quick, value: isPrimaryEnabled)
                .padding(.horizontal, 32)

            if let secondaryTitle {
                Button {
                    UIImpactFeedbackGenerator(style: .light).impactOccurred()
                    secondaryAction?()
                } label: {
                    Text(secondaryTitle)
                        .font(.makanBody(15).weight(.semibold))
                        .foregroundStyle(Color.kicap.opacity(0.7))
                        .frame(maxWidth: .infinity, minHeight: 44)
                        .contentShape(Rectangle())
                }
                .padding(.horizontal, 32)
            }
        }
        .padding(.top, 12)
        .padding(.bottom, 16)
        .background(Color.nasiCream)
    }
}

#Preview {
    OnboardingView(onFinished: {})
        .environment(LocationService())
}
