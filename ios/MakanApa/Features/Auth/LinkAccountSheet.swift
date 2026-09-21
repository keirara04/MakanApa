import SwiftUI
import UIKit

/// Shown when /auth/apple or /auth/google resolves to an existing password account instead of
/// logging in or creating a new one. Deliberately reassuring copy, not security-error-ish — this
/// is a normal, expected path, not a warning. Sends only a password + the opaque linkToken to
/// the backend (see AuthStore.completeLink) — never a provider identity.
struct LinkAccountSheet: View {
    let linkToken: String
    let email: String
    let provider: String

    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var password = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var shakeTrigger = 0
    @State private var linked = false

    private let accent = Color(red: 0.70, green: 0.18, blue: 0.12)

    var body: some View {
        VStack(spacing: 20) {
            if linked {
                VStack(spacing: 10) {
                    Image(systemName: "checkmark.circle.fill")
                        .font(.system(size: 40))
                        .foregroundStyle(.green)
                    Text("\(provider) connected")
                        .font(.system(.title3, design: .rounded, weight: .bold))
                }
                .transition(.opacity.combined(with: .scale(scale: 0.9)))
            } else {
                VStack(spacing: 8) {
                    Text("Looks like you've been here before 👀")
                        .font(.system(.title3, design: .rounded, weight: .bold))
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                    Text("An account already exists with \(email). Enter your password once and we'll connect \(provider) to the same MakanApa account.")
                        .font(.system(.subheadline, design: .rounded))
                        .foregroundStyle(Color.kicap.opacity(0.7))
                        .multilineTextAlignment(.center)
                        .fixedSize(horizontal: false, vertical: true)
                }

                VStack(alignment: .leading, spacing: 7) {
                    SecureField("Password", text: $password, prompt: Text("Your password").foregroundStyle(Color.kicap.opacity(0.45)))
                        .textContentType(.password)
                        .submitLabel(.go)
                        .onSubmit { submitIfReady() }
                        .font(.system(.body, design: .rounded))
                        .padding(.horizontal, 16)
                        .frame(minHeight: 56)
                        .background(Color.white)
                        .clipShape(RoundedRectangle(cornerRadius: 16))
                        .modifier(LoginFieldStyle(isFocused: false, accent: accent))

                    if let errorMessage {
                        Label(errorMessage, systemImage: "exclamationmark.circle.fill")
                            .font(.system(.subheadline, design: .rounded))
                            .foregroundStyle(accent)
                    }
                }
                .disabled(isSubmitting)
                .modifier(ShakeEffect(trigger: reduceMotion ? 0 : shakeTrigger))
                .animation(reduceMotion ? nil : .linear(duration: 0.4), value: shakeTrigger)

                MakanPrimaryButton(title: isSubmitting ? "Linking…" : "Link & continue") {
                    submitIfReady()
                }
                .disabled(isSubmitting || password.isEmpty)
                .opacity(isSubmitting || password.isEmpty ? 0.6 : 1)

                Button("Not now") { dismiss() }
                    .font(.system(.subheadline, design: .rounded))
                    .foregroundStyle(Color.kicap.opacity(0.7))
                    .frame(minHeight: 44)
            }
        }
        .padding(28)
        .presentationDetents([.medium])
        .foregroundStyle(Color.kicap)
        .interactiveDismissDisabled(isSubmitting)
    }

    private func submitIfReady() {
        guard !isSubmitting, !password.isEmpty else { return }
        Task { await submit() }
    }

    @MainActor
    private func submit() async {
        errorMessage = nil
        isSubmitting = true
        defer { isSubmitting = false }

        do {
            try await AuthStore.shared.completeLink(password: password, linkToken: linkToken)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            withAnimation(reduceMotion ? nil : .spring(duration: 0.35, bounce: 0.3)) {
                linked = true
            }
            try? await Task.sleep(for: .seconds(0.9))
            dismiss()
        } catch {
            errorMessage = "That password doesn't match this account."
            shakeTrigger += 1
            UINotificationFeedbackGenerator().notificationOccurred(.error)
        }
    }
}

#Preview {
    LinkAccountSheet(linkToken: "preview-token", email: "hakeem@example.com", provider: "Google")
}
