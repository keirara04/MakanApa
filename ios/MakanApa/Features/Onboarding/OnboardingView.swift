import SwiftUI
import UIKit

/// Runs once, before the auth gate (see MakanApaApp.swift): show the product working, get
/// location, ask halal — then straight into the app as a guest. No auth language anywhere in
/// here on purpose; an account is offered later, when there's something worth saving.
/// Cravings aren't asked here either — `TastePromptSheet` asks after a real pick.
struct OnboardingView: View {
    let onFinished: () -> Void
    /// Set when replayed from Settings — shows a Close button so the intro isn't a trap.
    var onClose: (() -> Void)? = nil

    @State private var page = 0
    /// Which way the last move went, so Back slides the other way. Set a beat before `page` —
    /// the outgoing page only picks up a new transition if it re-renders with it first.
    @State private var isMovingForward = true
    @State private var isFinishing = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private let pageCount = 3
    private var isFirstRun: Bool { onClose == nil }

    var body: some View {
        VStack(spacing: 0) {
            topBar
                .padding(.top, 8)

            ZStack {
                switch page {
                case 0:
                    OnboardingHeroPage(
                        onContinue: { advance(from: 0) },
                        // Replay is already signed in — no "I already have an account" there.
                        showsSignIn: isFirstRun,
                        onHaveAccount: signInInstead
                    )
                    .transition(pageTransition)
                case 1:
                    OnboardingLocationPage(onContinue: { advance(from: 1) })
                        .transition(pageTransition)
                default:
                    OnboardingHalalPage(isBusy: isFinishing, onContinue: finish)
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

    /// Back on the left, progress in the middle, Close (replay only) on the right. The right slot
    /// is always 44pt wide so the dots stay centred.
    private var topBar: some View {
        HStack {
            Button {
                go(to: page - 1)
            } label: {
                Image(systemName: "chevron.left")
                    .font(.body.weight(.semibold))
                    .foregroundStyle(Color.kicap)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .opacity(page > 0 && !isFinishing ? 1 : 0)
            .disabled(page == 0 || isFinishing)
            .accessibilityLabel("Back")
            .accessibilityHidden(page == 0)
            .sensoryFeedback(.selection, trigger: page) { old, new in new < old }

            Spacer()
            progressDots
            Spacer()

            if let onClose {
                Button(action: onClose) {
                    Image(systemName: "xmark")
                        .font(.body.weight(.semibold))
                        .foregroundStyle(Color.kicap)
                        .frame(width: 44, height: 44)
                        .contentShape(Rectangle())
                }
                .accessibilityLabel("Close")
            } else {
                Color.clear.frame(width: 44, height: 44)
            }
        }
        .padding(.horizontal, 12)
    }

    private var progressDots: some View {
        HStack(spacing: 6) {
            ForEach(0..<pageCount, id: \.self) { index in
                Capsule()
                    .fill(index <= page ? Color.sambalRed : Color.hairline)
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

    /// First run with no saved token: become a guest *before* completing, so the root swaps
    /// straight to the app — never a login-screen frame in between. A kept keychain token
    /// (reinstall) means `bootstrap()` is already restoring that account; don't make a second.
    /// Offline or failed: complete anyway and let the login screen's guest button be the retry.
    private func finish() {
        guard !isFinishing else { return }
        guard isFirstRun, CredentialStore.shared.token == nil else {
            complete()
            return
        }
        isFinishing = true
        Task {
            try? await AuthStore.shared.continueAsGuest()
            complete()
        }
    }

    /// Returning user on a new phone: skip ahead to sign-in. Location and halal are covered later
    /// (the location prompt route, and the account's halal setting on sign-in).
    private func signInInstead() {
        complete()
    }

    private func complete() {
        OnboardingState.shared.complete()
        onFinished()
    }
}

// MARK: - Pages

/// Shows the product instead of describing it: a pick card shuffling through dishes and
/// settling on one, with Bubu peeking over the corner.
private struct OnboardingHeroPage: View {
    let onContinue: () -> Void
    let showsSignIn: Bool
    let onHaveAccount: () -> Void

    var body: some View {
        OnboardingPageLayout(
            content: { PickDemoCard() },
            headline: Copy.onboardingHeroTitle,
            subtext: Copy.onboardingHeroSubtext,
            primaryTitle: Copy.onboardingHeroCTA,
            primaryAction: onContinue,
            secondaryTitle: showsSignIn ? Copy.onboardingHaveAccount : nil,
            secondaryAction: onHaveAccount
        )
    }
}

/// Seen once, so it's allowed to loop: shuffle fast-then-slow like a slot settling, land with a
/// "Picked for you" badge, hold, repeat. Reduce Motion gets the settled card, still.
private struct PickDemoCard: View {
    private struct Sample {
        let image: String
        let name: String
        let meta: String
    }

    private let samples = [
        Sample(image: "MoodNasiLemak", name: "Nasi lemak", meta: "350 m · ≈ RM10/person"),
        Sample(image: "MoodCharKueyTeow", name: "Char kuey teow", meta: "800 m · ≈ RM10/person"),
        Sample(image: "MoodDimSum", name: "Dim sum", meta: "1.2 km · ≈ RM20/person"),
        Sample(image: "MoodNasiKandar", name: "Nasi kandar", meta: "600 m · ≈ RM20/person"),
    ]

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var index = 0
    @State private var settled = false

    var body: some View {
        let sample = samples[index]
        VStack(alignment: .leading, spacing: 0) {
            Image(sample.image)
                .resizable()
                .scaledToFill()
                .frame(height: 160)
                .frame(maxWidth: .infinity)
                .clipped()
                .id(index)
                .transition(.push(from: .bottom))

            VStack(alignment: .leading, spacing: 4) {
                Text(sample.name)
                    .font(.headline)
                    .foregroundStyle(Color.kicap)
                Text(sample.meta)
                    .font(.subheadline)
                    .monospacedDigit()
                    .foregroundStyle(Color.kicapSecondary)
            }
            .contentTransition(.opacity)
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .frame(width: 260)
        .background(Color.surface)
        .clipShape(.card)
        .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
        .overlay(alignment: .topLeading) {
            if settled {
                Label(Copy.pickedForYou, systemImage: "checkmark")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(Color.sambalRed, in: Capsule())
                    .padding(12)
                    .transition(.scale(scale: 0.9).combined(with: .opacity))
            }
        }
        .shadow(color: Color.kicap.opacity(0.10), radius: 24, y: 12)
        .overlay(alignment: .bottomTrailing) {
            MascotView(mood: .idle, size: 76)
                .offset(x: 30, y: 26)
        }
        .padding(.bottom, 26)
        .accessibilityHidden(true)
        .task { await shuffle() }
    }

    private func shuffle() async {
        guard !reduceMotion else {
            settled = true
            return
        }
        while !Task.isCancelled {
            withAnimation(Motion.quick) { settled = false }
            for delay in [140, 150, 170, 210, 260, 340] {
                try? await Task.sleep(for: .milliseconds(delay))
                guard !Task.isCancelled else { return }
                withAnimation(Motion.quick) { index = (index + 1) % samples.count }
            }
            withAnimation(Motion.playful) { settled = true }
            try? await Task.sleep(for: .seconds(2.6))
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
                .frame(width: 160, height: 160)
                .accessibilityHidden(true)
                // Overlaid, not stacked, so the confirmation never pushes the headline down.
                .overlay(alignment: .bottom) {
                    if showConfirmation {
                        Label(Copy.onboardingLocationConfirmed, systemImage: "checkmark.circle.fill")
                            .font(.footnote.weight(.semibold))
                            .foregroundStyle(Color.pandan)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 6)
                            .background(Color.surface, in: Capsule())
                            .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
                            .fixedSize()
                            .offset(y: 18)
                            .transition(.opacity.combined(with: .scale(scale: 0.9)))
                    }
                }
            },
            headline: Copy.onboardingLocationTitle,
            subtext: Copy.onboardingLocationSubtext,
            footnote: OnboardingFootnote(icon: "lock.fill", text: Copy.onboardingLocationFootnote),
            // App Review (guideline 5.1.1(iv)): a pre-permission screen must use neutral wording
            // and always lead to the system prompt — no "Maybe later" to dodge it. The system
            // dialog itself is where the user says no.
            primaryTitle: "Continue",
            primaryAction: requestLocation,
            // Waiting on the system prompt — a second tap would only re-ask.
            isPrimaryEnabled: !didRequest || didFinish
        )
        .sensoryFeedback(.success, trigger: showConfirmation) { _, shown in shown }
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
/// anytime with the "Hide non-halal" chip on the Nearby map. Last page, so either answer
/// finishes onboarding (and may wait on guest sign-in — hence `isBusy`).
private struct OnboardingHalalPage: View {
    let isBusy: Bool
    let onContinue: () -> Void

    var body: some View {
        OnboardingPageLayout(
            content: {
                MascotView(mood: .idle, size: 136)
                    .overlay(alignment: .bottomTrailing) {
                        Image(systemName: "checkmark.seal.fill")
                            .font(.largeTitle)
                            .foregroundStyle(Color.pandan)
                            .padding(6)
                            .background(Color.nasiCream, in: Circle())
                            .offset(x: 10, y: 4)
                    }
                    .frame(height: 160)
                    .accessibilityHidden(true)
            },
            headline: Copy.onboardingHalalTitle,
            subtext: Copy.onboardingHalalSubtext,
            footnote: OnboardingFootnote(icon: "info.circle.fill", text: Copy.onboardingHalalFootnote),
            primaryTitle: Copy.onboardingHalalYes,
            primaryAction: {
                HalalPreference.isOn = true
                onContinue()
            },
            isBusy: isBusy,
            secondaryTitle: Copy.onboardingHalalNo,
            secondaryAction: {
                HalalPreference.isOn = false
                onContinue()
            }
        )
    }
}

// MARK: - Taste prompt

/// "Want sharper picks?" — shown after a real accepted pick (see ResultView.afterAccept()), when
/// the user has just seen what a pick is. Grouped so food and style don't read as one muddled
/// list; the 8 options are the server's fixed seed keys.
struct TastePromptSheet: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var state = OnboardingState.shared
    /// Bumped when a tap is refused at the limit — shakes the counter so the limit is seen.
    @State private var limitNudge = 0
    @State private var toggles = 0

    private let columns = [GridItem(.adaptive(minimum: 100), spacing: 10)]
    private let maxPicks = OnboardingState.maxPicks
    private var isFull: Bool { state.selectedCuisines.count >= maxPicks }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 24) {
                    VStack(alignment: .leading, spacing: 6) {
                        Text(Copy.tasteTitle)
                            .font(.title2.weight(.bold))
                            .foregroundStyle(Color.kicap)
                            .accessibilityAddTraits(.isHeader)
                        Text(Copy.tasteSubtext)
                            .font(.subheadline)
                            .foregroundStyle(Color.kicapSecondary)
                    }

                    group(Copy.tasteFoodHeader, keys: OnboardingState.foodPicks)
                    group(Copy.tasteStyleHeader, keys: OnboardingState.stylePicks + [OnboardingState.anythingPick])

                    Text(counterText)
                        .font(.footnote.weight(.semibold))
                        .foregroundStyle(isFull ? Color.sambalRed : Color.kicapSecondary)
                        .contentTransition(.numericText())
                        .modifier(ShakeEffect(trigger: reduceMotion ? 0 : limitNudge))
                        .animation(.easeOut(duration: 0.35), value: limitNudge)
                        .animation(Motion.quick, value: state.selectedCuisines.count)
                }
                .padding(20)
            }
            .background(Color.nasiCream.ignoresSafeArea())
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(Copy.tasteNotNow) { close(saved: false) }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(Copy.tasteSave) { close(saved: true) }
                        .fontWeight(.semibold)
                        .disabled(state.selectedCuisines.isEmpty)
                }
            }
        }
        .sensoryFeedback(.selection, trigger: toggles)
        .sensoryFeedback(.warning, trigger: limitNudge)
    }

    /// Either button stops future asks; only Save sends the picks.
    private func close(saved: Bool) {
        state.finishTastePrompt(saved: saved)
        dismiss()
    }

    private func group(_ title: String, keys: [String]) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(title)
                .font(.footnote.weight(.semibold))
                .foregroundStyle(Color.kicapSecondary)
                .accessibilityAddTraits(.isHeader)
            LazyVGrid(columns: columns, spacing: 10) {
                ForEach(keys, id: \.self) { chip($0) }
            }
        }
    }

    private var counterText: String {
        let count = state.selectedCuisines.count
        return count == 0 ? Copy.tasteNonePicked : Copy.tastePickedCount(count, of: maxPicks)
    }

    private func chip(_ key: String) -> some View {
        let isSelected = state.selectedCuisines.contains(key)
        // "Anything" replaces the others instead of adding to them, so it's never locked.
        let isLocked = isFull && !isSelected && key != OnboardingState.anythingPick

        return Button {
            if isLocked {
                limitNudge += 1
                return
            }
            toggles += 1
            withAnimation(reduceMotion ? .easeOut(duration: 0.12) : Motion.playful) {
                state.toggleCuisine(key)
            }
        } label: {
            HStack(spacing: 5) {
                if isSelected {
                    Image(systemName: "checkmark")
                        .font(.caption.weight(.bold))
                        .transition(.scale.combined(with: .opacity))
                }
                Text(Copy.tasteLabel(key))
                    .lineLimit(1)
                    .minimumScaleFactor(0.85)
            }
            .font(.subheadline.weight(.medium))
            .foregroundStyle(isSelected ? .white : Color.kicap)
            .padding(.horizontal, 14)
            .frame(maxWidth: .infinity, minHeight: 44)
            .background(isSelected ? Color.sambalRed : Color.surface, in: Capsule())
            .overlay(Capsule().strokeBorder(isSelected ? .clear : Color.hairline, lineWidth: 1))
            .opacity(isLocked ? 0.45 : 1)
        }
        .buttonStyle(PressCompressStyle())
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityHint(isLocked ? "You've picked \(maxPicks). Unpick one first." : "")
    }
}

// MARK: - Layout

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
    /// A finishing network call is in flight: both buttons lock, a spinner replaces the secondary.
    var isBusy = false
    var secondaryTitle: String? = nil
    var secondaryAction: (() -> Void)? = nil

    var body: some View {
        // Scrolls only when it has to (small phones, large text) — otherwise centred as before,
        // and the buttons stay pinned either way.
        GeometryReader { proxy in
            ScrollView {
                VStack(spacing: 32) {
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
                .font(.title.weight(.bold))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
                .accessibilityAddTraits(.isHeader)

            Text(subtext)
                .font(.body)
                .foregroundStyle(Color.kicapSecondary)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)

            if let footnote {
                Label(footnote.text, systemImage: footnote.icon)
                    .font(.footnote)
                    .foregroundStyle(Color.kicapSecondary)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                    .padding(.top, 4)
            }
        }
        .padding(.horizontal, 32)
    }

    private var buttons: some View {
        VStack(spacing: 4) {
            MakanPrimaryButton(title: primaryTitle, action: primaryAction)
                .disabled(!isPrimaryEnabled || isBusy)
                .opacity(isPrimaryEnabled && !isBusy ? 1 : 0.45)
                .animation(Motion.quick, value: isPrimaryEnabled && !isBusy)

            if isBusy {
                ProgressView()
                    .tint(Color.kicapSecondary)
                    .frame(minHeight: 44)
            } else if let secondaryTitle {
                Button {
                    secondaryAction?()
                } label: {
                    Text(secondaryTitle)
                        .font(.body.weight(.semibold))
                        .foregroundStyle(Color.kicapSecondary)
                        .frame(maxWidth: .infinity, minHeight: 44)
                        .contentShape(Rectangle())
                }
            }
        }
        .padding(.horizontal, 32)
        .padding(.top, 12)
        .padding(.bottom, 16)
        .background(Color.nasiCream)
    }
}

#Preview {
    OnboardingView(onFinished: {})
        .environment(LocationService())
}

#Preview("Taste prompt") {
    TastePromptSheet()
}
