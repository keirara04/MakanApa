import SwiftUI
import UIKit

/// One-tap sign-in for prompts shown to a guest — Sign in with Apple first, then Google, with
/// email / create account one step away. Signing in from a guest session upgrades that same
/// account server-side, so the guest's picks, saves and taste profile all carry over.
struct QuickSignInPanel: View {
    /// Credited to the resulting sign-up — see `AuthStore.signupSource`.
    let source: String

    @State private var errorMessage: String?
    @State private var pendingLink: QuickSignInLink?
    @State private var showingMoreOptions = false

    var body: some View {
        VStack(spacing: 10) {
            SocialSignInButtons(
                onNeedsLinking: { linkToken, email, provider in
                    pendingLink = QuickSignInLink(linkToken: linkToken, email: email, provider: provider)
                },
                onError: { message in
                    errorMessage = message
                    UINotificationFeedbackGenerator().notificationOccurred(.error)
                },
                onStart: {
                    errorMessage = nil
                    AuthStore.shared.signupSource = source
                }
            )

            if let errorMessage {
                Label(errorMessage, systemImage: "exclamationmark.circle.fill")
                    .font(.makanBody(13))
                    .foregroundStyle(Color.sambalRed)
                    .fixedSize(horizontal: false, vertical: true)
            }

            Button("Use email instead") { showingMoreOptions = true }
                .font(.makanBody(14).weight(.semibold))
                .foregroundStyle(Color.kicap.opacity(0.75))
                .frame(minHeight: 44)
        }
        .sheet(item: $pendingLink) { link in
            LinkAccountSheet(linkToken: link.linkToken, email: link.email, provider: link.provider)
        }
        .accountSignInSheet(isPresented: $showingMoreOptions, source: source)
    }
}

private struct QuickSignInLink: Identifiable {
    let linkToken: String
    let email: String
    let provider: String
    var id: String { linkToken }
}
