import SwiftUI

/// App Review guideline 5.1.1(v): recommendations and Nearby work for guests (see
/// `AuthStore.continueAsGuest()`) — only account-based features need a real account. Wrap an
/// account-based screen in this: a guest sees `AccountRequiredPrompt` in its place, and the
/// real screen appears as soon as they sign in (the session is observed).
struct AccountRequired<Content: View>: View {
    /// Completes "Sign in to …", e.g. "add a place".
    let feature: String
    @ViewBuilder let content: () -> Content

    private var authStore = AuthStore.shared

    init(feature: String, @ViewBuilder content: @escaping () -> Content) {
        self.feature = feature
        self.content = content
    }

    var body: some View {
        if authStore.session.isGuest {
            AccountRequiredPrompt(feature: feature)
        } else {
            content()
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
}
