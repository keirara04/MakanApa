import SwiftUI
import UIKit

/// Password field always shown for simplicity (the client doesn't know whether the signed-in
/// account has a password vs. is social-only) — the backend just ignores it for social-only
/// accounts, so leaving it blank there still works.
struct DeleteAccountSheet: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var password = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var shakeTrigger = 0

    private let accent = Color(red: 0.70, green: 0.18, blue: 0.12)

    var body: some View {
        VStack(spacing: 20) {
            VStack(spacing: 8) {
                Image(systemName: "exclamationmark.triangle.fill")
                    .font(.system(size: 32))
                    .foregroundStyle(accent)
                Text("Delete your account?")
                    .font(.system(.title3, design: .rounded, weight: .bold))
                    .accessibilityAddTraits(.isHeader)
                Text("This permanently deletes your account, profile, community posts, photos and taste profile. Places you added, your past picks and saves stay in MakanApa without your name. This can't be undone.")
                    .font(.system(.subheadline, design: .rounded))
                    .foregroundStyle(Color.kicap.opacity(0.7))
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
            }

            VStack(alignment: .leading, spacing: 7) {
                // A guest account has no password (and nothing to prove beyond its token).
                if !AuthStore.shared.session.isGuest {
                    SecureField("Password", text: $password, prompt: Text("Password (leave blank for Apple/Google accounts)").foregroundStyle(Color.kicap.opacity(0.4)))
                        .textContentType(.password)
                        .submitLabel(.go)
                        .onSubmit { submit() }
                        .font(.system(.body, design: .rounded))
                        .padding(.horizontal, 16)
                        .frame(minHeight: 56)
                        .background(Color.white)
                        .clipShape(RoundedRectangle(cornerRadius: 16))
                        .modifier(LoginFieldStyle(isFocused: false, accent: accent))
                }

                if let errorMessage {
                    Label(errorMessage, systemImage: "exclamationmark.circle.fill")
                        .font(.system(.subheadline, design: .rounded))
                        .foregroundStyle(accent)
                }
            }
            .disabled(isSubmitting)
            .modifier(ShakeEffect(trigger: reduceMotion ? 0 : shakeTrigger))
            .animation(reduceMotion ? nil : .linear(duration: 0.4), value: shakeTrigger)

            Button(role: .destructive) {
                submit()
            } label: {
                HStack(spacing: 10) {
                    if isSubmitting {
                        ProgressView().tint(.white)
                    }
                    Text(isSubmitting ? "Deleting…" : "Delete my account")
                }
                .font(.system(.headline, design: .rounded))
                .frame(maxWidth: .infinity, minHeight: 56)
            }
            .buttonStyle(LoginButtonStyle(accent: accent, isLoading: isSubmitting))
            .disabled(isSubmitting)

            Button("Cancel") { dismiss() }
                .font(.system(.subheadline, design: .rounded))
                .foregroundStyle(Color.kicap.opacity(0.7))
                .frame(minHeight: 44)
                .disabled(isSubmitting)
        }
        .padding(28)
        .presentationDetents([.medium])
        .foregroundStyle(Color.kicap)
        .interactiveDismissDisabled(isSubmitting)
    }

    private func submit() {
        guard !isSubmitting else { return }
        Task { await performDelete() }
    }

    @MainActor
    private func performDelete() async {
        errorMessage = nil
        isSubmitting = true
        defer { isSubmitting = false }

        do {
            try await AuthStore.shared.deleteAccount(password: password.isEmpty ? nil : password)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            dismiss()
        } catch {
            errorMessage = "Couldn't delete your account. Check your password and try again."
            shakeTrigger += 1
            UINotificationFeedbackGenerator().notificationOccurred(.error)
        }
    }
}

#Preview {
    DeleteAccountSheet()
}
