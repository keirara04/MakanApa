import AuthenticationServices
import GoogleSignIn
import SwiftUI
import UIKit

/// Apple/Google buttons are both login *and* signup at once — the backend's find-by-sub logic
/// decides which — so callers never need to know or ask which case they're in. `onNeedsLinking`
/// fires when the identity resolves to an existing password account; the caller is responsible
/// for presenting whatever collects the password + completes the link (see LoginView).
struct SocialSignInButtons: View {
    var onNeedsLinking: (_ linkToken: String, _ email: String, _ provider: String) -> Void
    var onError: (String) -> Void
    /// Fires the moment either button is tapped, before any network call — lets a prompt stamp
    /// `AuthStore.signupSource` at tap time rather than on appear (several prompts can be on screen).
    var onStart: (() -> Void)? = nil

    @State private var currentAppleNonce: String?
    @State private var isGoogleSigningIn = false

    var body: some View {
        VStack(spacing: 12) {
            SignInWithAppleButton(.continue) { request in
                onStart?()
                let nonce = AppleNonce.randomRawNonce()
                currentAppleNonce = nonce
                request.requestedScopes = [.fullName, .email]
                request.nonce = AppleNonce.sha256Hex(nonce)
            } onCompletion: { result in
                handleAppleCompletion(result)
            }
            .signInWithAppleButtonStyle(.black)
            .frame(height: 52)
            .clipShape(RoundedRectangle(cornerRadius: 16))

            googleButton
        }
    }

    private var googleButton: some View {
        Button(action: handleGoogleTap) {
            HStack(spacing: 10) {
                if isGoogleSigningIn {
                    ProgressView()
                } else {
                    Image("GoogleLogo")
                        .resizable()
                        .scaledToFit()
                        .frame(width: 18, height: 18)
                        .accessibilityHidden(true)
                }
                Text(isGoogleSigningIn ? "Signing in…" : "Continue with Google")
                    .font(.system(.headline, design: .rounded, weight: .semibold))
            }
            .foregroundStyle(Color.kicap)
            .frame(maxWidth: .infinity, minHeight: 52)
        }
        .background(Color.white, in: RoundedRectangle(cornerRadius: 16))
        .overlay {
            RoundedRectangle(cornerRadius: 16)
                .strokeBorder(Color.kicap.opacity(0.15), lineWidth: 1)
        }
        .disabled(isGoogleSigningIn)
    }

    private func handleGoogleTap() {
        onStart?()
        guard let presenting = presentingViewController() else {
            onError("Couldn't start Google sign-in.")
            return
        }

        isGoogleSigningIn = true
        Task { @MainActor in
            defer { isGoogleSigningIn = false }
            do {
                let result = try await GIDSignIn.sharedInstance.signIn(withPresenting: presenting)
                guard let idToken = result.user.idToken?.tokenString else {
                    onError("Couldn't complete Google sign-in.")
                    return
                }

                let outcome = try await AuthStore.shared.loginWithGoogle(idToken: idToken)
                switch outcome {
                case .authenticated:
                    UINotificationFeedbackGenerator().notificationOccurred(.success)
                case .needsLinking(let linkToken, let email):
                    onNeedsLinking(linkToken, email, "Google")
                }
            } catch let signInError as GIDSignInError where signInError.code == .canceled {
                // User dismissed the sheet — not an error worth surfacing.
            } catch {
                onError("Couldn't sign in with Google. Try again in a bit.")
            }
        }
    }

    private func presentingViewController() -> UIViewController? {
        UIApplication.shared.connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .first?
            .windows
            .first(where: \.isKeyWindow)?
            .rootViewController
    }

    private func handleAppleCompletion(_ result: Result<ASAuthorization, Error>) {
        guard let nonce = currentAppleNonce else { return }

        switch result {
        case .success(let authorization):
            handleAppleAuthorization(authorization, rawNonce: nonce)
        case .failure(let error):
            // Cancelling the sheet is not an error worth surfacing.
            if (error as? ASAuthorizationError)?.code != .canceled {
                onError("Couldn't sign in with Apple.")
            }
        }
    }

    private func handleAppleAuthorization(_ authorization: ASAuthorization, rawNonce: String) {
        guard let credential = authorization.credential as? ASAuthorizationAppleIDCredential,
              let tokenData = credential.identityToken,
              let identityToken = String(data: tokenData, encoding: .utf8),
              let codeData = credential.authorizationCode,
              let authorizationCode = String(data: codeData, encoding: .utf8)
        else {
            onError("Couldn't complete Sign in with Apple.")
            return
        }

        // Only present on the very first authorization for a given Apple ID — never sent again
        // on subsequent sign-ins, so this is the one and only chance to capture it.
        let fullName = [credential.fullName?.givenName, credential.fullName?.familyName]
            .compactMap { $0 }
            .joined(separator: " ")
            .trimmingCharacters(in: .whitespaces)

        Task { @MainActor in
            do {
                let outcome = try await AuthStore.shared.loginWithApple(
                    identityToken: identityToken,
                    authorizationCode: authorizationCode,
                    rawNonce: rawNonce,
                    fullName: fullName.isEmpty ? nil : fullName
                )
                switch outcome {
                case .authenticated:
                    UINotificationFeedbackGenerator().notificationOccurred(.success)
                case .needsLinking(let linkToken, let email):
                    onNeedsLinking(linkToken, email, "Apple")
                }
            } catch {
                onError("Couldn't sign in with Apple. Try again in a bit.")
            }
        }
    }
}
