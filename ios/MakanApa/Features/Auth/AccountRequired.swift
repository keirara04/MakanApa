import SwiftUI
import UIKit

/// App Review guideline 5.1.1(v): recommendations and Nearby work for guests (see
/// `AuthStore.continueAsGuest()`) — only account-based features need a real account. Wrap an
/// account-based screen in this: a guest sees `AccountRequiredPrompt` in its place, and the
/// real screen appears as soon as they sign in (the session is observed).
///
/// Contributions (posting, adding places or photos, halal vouches) additionally need agreement
/// to the current Terms + Community Guidelines (guideline 1.2) — a signed-in user who hasn't
/// agreed sees `CommunityAgreementPrompt` first. The server enforces the same rule.
struct AccountRequired<Content: View>: View {
    /// Completes "Sign in to …", e.g. "add a place".
    let feature: String
    /// False for account-based screens that only read (e.g. My places).
    let requiresTerms: Bool
    @ViewBuilder let content: () -> Content

    private var authStore = AuthStore.shared

    init(feature: String, requiresTerms: Bool = true, @ViewBuilder content: @escaping () -> Content) {
        self.feature = feature
        self.requiresTerms = requiresTerms
        self.content = content
    }

    var body: some View {
        if authStore.session.isGuest {
            AccountRequiredPrompt(feature: feature)
        } else if requiresTerms, authStore.session.needsTermsAcceptance {
            CommunityAgreementPrompt()
        } else {
            content()
        }
    }
}

/// The blocking agreement before someone's first contribution — and again after the Terms or
/// Guidelines change materially. Clickwrap, not just a link: Apple's 1.2 reviews expect users to
/// actively agree to terms with zero tolerance for objectionable content and abusive users, and
/// under-18s can't contract alone in Malaysia, so the checkbox covers parental agreement.
struct CommunityAgreementPrompt: View {
    @Environment(\.dismiss) private var dismiss
    @State private var hasAgreed = false
    @State private var isSubmitting = false
    @State private var errorMessage: String?

    private var authStore = AuthStore.shared

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                VStack(spacing: 12) {
                    MascotView(mood: .idle, size: 96)
                    Text("Before you contribute")
                        .font(.makanDisplay(22))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                    Text("Posts, places, photos and halal vouches are seen by other people, so everyone agrees to the same rules first.")
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap.opacity(0.75))
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity)

                VStack(alignment: .leading, spacing: 10) {
                    rule("Keep it about food and places, and be kind.")
                    rule("No hate, harassment, sexual content, spam or links.")
                    rule("Only vouch for halal info you've seen yourself.")
                    rule("Zero tolerance: we review reports within 24 hours and remove content or accounts that break the rules.")
                }
                .padding(16)
                .background(Color.white, in: RoundedRectangle(cornerRadius: 18))

                Text(documentLinks)
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap.opacity(0.75))
                    .tint(Color.sambalRed)
                    .multilineTextAlignment(.center)
                    .frame(maxWidth: .infinity)

                Button {
                    hasAgreed.toggle()
                    UISelectionFeedbackGenerator().selectionChanged()
                } label: {
                    HStack(alignment: .top, spacing: 12) {
                        Image(systemName: hasAgreed ? "checkmark.square.fill" : "square")
                            .font(.system(size: 22))
                            .foregroundStyle(hasAgreed ? Color.sambalRed : Color.kicap.opacity(0.5))
                        Text("I'm 13 or older (if I'm under 18, my parent or guardian agrees), and I agree to the Terms of Use and Community Guidelines.")
                            .font(.makanBody(14))
                            .foregroundStyle(Color.kicap)
                            .multilineTextAlignment(.leading)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityAddTraits(hasAgreed ? [.isSelected] : [])

                if let errorMessage {
                    Label(errorMessage, systemImage: "exclamationmark.circle.fill")
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .fixedSize(horizontal: false, vertical: true)
                }

                VStack(spacing: 10) {
                    MakanPrimaryButton(title: isSubmitting ? "Saving…" : "I agree") {
                        Task { await agree() }
                    }
                    .disabled(!hasAgreed || isSubmitting)
                    .opacity(hasAgreed ? 1 : 0.5)

                    Button("Not now") { dismiss() }
                        .font(.makanBody(14))
                        .foregroundStyle(.secondary)
                        .frame(minHeight: 44)
                }
                .frame(maxWidth: .infinity)
            }
            .padding(24)
        }
        .background(Color.nasiCream.ignoresSafeArea())
    }

    private func rule(_ text: String) -> some View {
        Label {
            Text(text)
                .font(.makanBody(14))
                .foregroundStyle(Color.kicap)
                .fixedSize(horizontal: false, vertical: true)
        } icon: {
            Image(systemName: "checkmark.circle.fill")
                .foregroundStyle(Color.pandan)
        }
    }

    /// One wrapping line rather than three side-by-side links, which overflow on small iPhones.
    private var documentLinks: AttributedString {
        var text = AttributedString("Read the full Terms of Use, Community Guidelines and Privacy Policy.")
        for (phrase, urlString) in [
            ("Terms of Use", Copy.termsURL),
            ("Community Guidelines", Copy.communityGuidelinesURL),
            ("Privacy Policy", Copy.privacyPolicyURL),
        ] {
            if let range = text.range(of: phrase), let url = URL(string: urlString) {
                text[range].link = url
                text[range].underlineStyle = .single
            }
        }
        return text
    }

    @MainActor
    private func agree() async {
        guard case .authenticated(let user) = authStore.session, let legal = user.legal else { return }
        errorMessage = nil
        isSubmitting = true
        defer { isSubmitting = false }

        do {
            try await authStore.acceptTerms(legal)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
        } catch {
            // 422 = the Terms changed since this screen loaded: pick up the new versions and let
            // them agree again rather than silently recording the old ones.
            await authStore.refreshUser()
            hasAgreed = false
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't save that. Check your connection and try again."
            UINotificationFeedbackGenerator().notificationOccurred(.error)
        }
    }
}

/// For inline contribution triggers that aren't a screen of their own (e.g. the quick-add photo
/// row): walks a guest through sign-in, then the agreement, and closes itself once both are done
/// so the trigger works on the next tap.
struct ContributionGateSheet: View {
    let feature: String
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        AccountRequired(feature: feature) {
            Color.nasiCream
                .ignoresSafeArea()
                .onAppear { dismiss() }
        }
    }
}

/// Shown instead of an account-based screen to a guest. Always sits in a sheet or a pushed
/// screen, so "Not now" dismisses back to wherever they came from.
struct AccountRequiredPrompt: View {
    let feature: String

    @Environment(\.dismiss) private var dismiss
    @State private var showingSignIn = false

    var body: some View {
        VStack(spacing: 20) {
            Spacer(minLength: 0)

            MascotView(mood: .idle, size: 110)

            VStack(spacing: 8) {
                Text("Sign in to \(feature)")
                    .font(.makanDisplay(22))
                    .foregroundStyle(Color.kicap)
                    .multilineTextAlignment(.center)
                    .accessibilityAddTraits(.isHeader)
                Text(Copy.accountRequiredDetail)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
            .padding(.horizontal, 32)

            Spacer(minLength: 0)

            VStack(spacing: 10) {
                MakanPrimaryButton(title: "Sign in or create account") { showingSignIn = true }

                Button("Not now") { dismiss() }
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .frame(minHeight: 44)
            }
            .padding(.horizontal, 32)
            .padding(.bottom, 16)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream.ignoresSafeArea())
        .accountSignInSheet(isPresented: $showingSignIn)
    }
}

/// LoginView as a sheet, for a guest who wants a real account. Closes itself once the session
/// is one — signing up from a guest session upgrades that same account server-side, so their
/// picks and saves carry over.
struct GuestSignInSheet: View {
    @Environment(\.dismiss) private var dismiss
    private var authStore = AuthStore.shared

    var body: some View {
        LoginView(onClose: { dismiss() })
            .onChange(of: authStore.session) { _, session in
                if session.isAuthenticated, !session.isGuest {
                    dismiss()
                }
            }
    }
}

extension View {
    func accountSignInSheet(isPresented: Binding<Bool>) -> some View {
        sheet(isPresented: isPresented) { GuestSignInSheet() }
    }

    func contributionGateSheet(isPresented: Binding<Bool>, feature: String) -> some View {
        sheet(isPresented: isPresented) { ContributionGateSheet(feature: feature) }
    }
}
