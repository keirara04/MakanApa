import SwiftUI
import UIKit

/// Runs once, before the auth gate (see MakanApaApp.swift), so a first-time opener sees the
/// value prop and gives location before anything ever asks for an account. No auth language
/// anywhere in here on purpose — "you're already in" is the whole point of this screen existing.
struct OnboardingView: View {
    let onFinished: () -> Void

    @State private var page = 0
    @Environment(LocationService.self) private var locationService
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private let pageCount = 4

    var body: some View {
        VStack(spacing: 0) {
            progressDots
                .padding(.top, 20)

            ZStack {
                if page == 0 {
                    OnboardingValuePropPage(onContinue: advance)
                        .transition(pageTransition)
                } else if page == 1 {
                    OnboardingLocationPage(onContinue: advance)
                        .transition(pageTransition)
                } else if page == 2 {
                    OnboardingHalalPage(onContinue: advance)
                        .transition(pageTransition)
                } else {
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
        return .asymmetric(
            insertion: .opacity.combined(with: .offset(x: 8)),
            removal: .opacity.combined(with: .offset(x: -8))
        )
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
    }

    private func advance() {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        page = min(page + 1, pageCount - 1)
    }

    private func finish() {
        OnboardingState.shared.complete()
        onFinished()
    }
}

private struct OnboardingValuePropPage: View {
    let onContinue: () -> Void

    var body: some View {
        OnboardingPageLayout(
            content: {
                ZStack {
                    ForEach(Array(floatingLabels.enumerated()), id: \.offset) { index, label in
                        FloatingLabelChip(text: label)
                            .offset(floatingOffset(for: index))
                    }
                    MascotView(mood: .idle, size: 140)
                }
                .frame(height: 220)
            },
            headline: "What should we makan? 👀",
            subtext: "Discover places nearby, hidden gems, and spots your community is actually picking.",
            primaryTitle: "Jom explore",
            primaryAction: onContinue,
            secondaryTitle: nil,
            secondaryAction: nil
        )
    }

    private let floatingLabels = ["RM", "Late night", "Cafe", "Community find"]

    private func floatingOffset(for index: Int) -> CGSize {
        switch index {
        case 0: return CGSize(width: -110, height: -70)
        case 1: return CGSize(width: 100, height: -50)
        case 2: return CGSize(width: -100, height: 60)
        default: return CGSize(width: 110, height: 75)
        }
    }
}

private struct FloatingLabelChip: View {
    let text: String

    var body: some View {
        Text(text)
            .font(.makanBody(11))
            .foregroundStyle(Color.kicap.opacity(0.7))
            .padding(.horizontal, 10)
            .padding(.vertical, 6)
            .background(Color.white, in: Capsule())
    }
}

private struct OnboardingLocationPage: View {
    let onContinue: () -> Void

    @Environment(LocationService.self) private var locationService
    @State private var didRequest = false
    @State private var showConfirmation = false

    var body: some View {
        OnboardingPageLayout(
            content: {
                ZStack {
                    Image("LocationMap")
                        .resizable()
                        .scaledToFit()
                        .opacity(0.18)
                        .accessibilityHidden(true)
                    Image("MascotLocation")
                        .resizable()
                        .scaledToFit()
                        .accessibilityHidden(true)
                }
                .frame(width: 150, height: 150)

                if showConfirmation {
                    Label("Nice, found you", systemImage: "checkmark.circle.fill")
                        .font(.makanBody(13))
                        .foregroundStyle(.green)
                        .transition(.opacity.combined(with: .scale(scale: 0.9)))
                }
            },
            headline: "Find good food around you 📍",
            subtext: "We use your location to show places nearby, what's trending around you, and better \"Pick one lah\" results.\n\nYour location isn't shown publicly.",
            // App Review (guideline 5.1.1(iv)): a pre-permission screen must use neutral wording
            // and always lead to the system prompt — no "Maybe later" to dodge it. The system
            // dialog itself is where the user says no.
            primaryTitle: "Continue",
            primaryAction: requestLocation,
            secondaryTitle: nil,
            secondaryAction: nil
        )
        .onChange(of: locationService.state) { _, newState in
            guard didRequest else { return }
            switch newState {
            case .authorized:
                confirmAndContinue()
            case .denied, .unavailable:
                onContinue()
            default:
                break
            }
        }
    }

    private func requestLocation() {
        didRequest = true

        // Already authorized from an earlier session — LocationService fetches on init, so the
        // state may already be .authorized before this page even appears. Re-requesting yields
        // the same coordinate, and onChange never fires for a value that hasn't actually
        // changed, which otherwise leaves this screen stuck forever. Advance immediately instead
        // of waiting for a delegate callback that has nothing new to report.
        switch locationService.state {
        case .authorized:
            confirmAndContinue()
            return
        case .denied, .unavailable:
            // Already decided in an earlier session — the system won't prompt again and the
            // state won't change, so waiting on onChange would leave this screen stuck.
            onContinue()
            return
        case .notDetermined:
            break
        }

        locationService.requestLocation()
    }

    private func confirmAndContinue() {
        UINotificationFeedbackGenerator().notificationOccurred(.success)
        withAnimation(Motion.playful) { showConfirmation = true }
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.6) { onContinue() }
    }
}

/// Asked once, up front — a dietary requirement shouldn't be buried in Settings. Changeable
/// anytime with the "Muslim-friendly" chip on the Nearby map.
private struct OnboardingHalalPage: View {
    let onContinue: () -> Void

    var body: some View {
        OnboardingPageLayout(
            content: {
                Image(systemName: "checkmark.seal.fill")
                    .font(.system(size: 72))
                    .foregroundStyle(Color.pandan)
                    .accessibilityHidden(true)
                    .frame(height: 150)
            },
            headline: "Do you only eat halal?",
            subtext: "We'll hide places known to be non-halal. Places we haven't verified yet still show, clearly marked — and you can help verify them. Switch it anytime with the Muslim-friendly chip on the map. This is still in testing, so always double-check at the restaurant.",
            primaryTitle: "Yes, halal only",
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

    private let cuisines = ["Mamak", "Cafe", "Korean", "Malay", "Dessert", "Cheap eats", "Late night", "Anything lah"]
    private let columns = [GridItem(.adaptive(minimum: 100), spacing: 10)]

    var body: some View {
        OnboardingPageLayout(
            content: {
                LazyVGrid(columns: columns, spacing: 10) {
                    ForEach(cuisines, id: \.self) { cuisine in
                        cuisineChip(cuisine)
                    }
                }
                .padding(.horizontal, 8)
            },
            headline: "What are you usually craving?",
            subtext: "Pick a few — you can change this anytime.",
            primaryTitle: "Start exploring",
            primaryAction: onFinish,
            secondaryTitle: "Skip for now",
            secondaryAction: onFinish
        )
    }

    private func cuisineChip(_ cuisine: String) -> some View {
        let isSelected = state.selectedCuisines.contains(cuisine)
        return Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            withAnimation(Motion.playful) {
                state.toggleCuisine(cuisine)
            }
        } label: {
            Text(cuisine)
                .font(.makanBody(14))
                .foregroundStyle(isSelected ? .white : Color.kicap)
                .padding(.horizontal, 14)
                .padding(.vertical, 10)
                .frame(maxWidth: .infinity)
                .background(isSelected ? Color.sambalRed : Color.white, in: Capsule())
        }
        .buttonStyle(PressCompressStyle())
    }
}

/// Shared skeleton: illustration/content up top, headline + subtext in the middle, CTA + skip
/// pinned to the bottom. Every onboarding page fits this shape, so the layout lives once here
/// instead of being re-typed per page.
private struct OnboardingPageLayout<Content: View>: View {
    @ViewBuilder let content: () -> Content
    let headline: String
    let subtext: String
    let primaryTitle: String
    let primaryAction: (() -> Void)?
    let secondaryTitle: String?
    let secondaryAction: (() -> Void)?

    var body: some View {
        VStack(spacing: 28) {
            Spacer(minLength: 0)

            content()

            VStack(spacing: 10) {
                Text(headline)
                    .font(.makanDisplay(24))
                    .foregroundStyle(Color.kicap)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                    .accessibilityAddTraits(.isHeader)

                Text(subtext)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .padding(.horizontal, 32)

            Spacer(minLength: 0)

            VStack(spacing: 14) {
                MakanPrimaryButton(title: primaryTitle) {
                    primaryAction?()
                }
                .padding(.horizontal, 32)

                if let secondaryTitle {
                    Button(secondaryTitle) {
                        secondaryAction?()
                    }
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .frame(minHeight: 44)
                }
            }
            .padding(.bottom, 16)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

#Preview {
    OnboardingView(onFinished: {})
        .environment(LocationService())
}
