import SwiftUI
import UIKit

/// Email/password signup only — Apple/Google are both login *and* signup at once from
/// LoginView directly, so they don't belong on this screen (see LoginView's doc comment).
struct SignUpView: View {
    private enum Field {
        case name, email, password
    }

    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var name = ""
    @State private var email = ""
    @State private var password = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var shakeTrigger = 0
    @State private var showPassword = false
    @FocusState private var focusedField: Field?

    private let accent = Color(red: 0.70, green: 0.18, blue: 0.12)
    private let paper = Color(red: 0.99, green: 0.98, blue: 0.96)

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Join MakanApa")
                        .font(.system(.title2, design: .rounded, weight: .bold))
                        .accessibilityAddTraits(.isHeader)
                    Text("Takes a minute. Your next favourite is waiting.")
                        .font(.system(.subheadline, design: .rounded))
                        .foregroundStyle(Color.kicap.opacity(0.7))
                }
                .padding(.top, 12)

                VStack(alignment: .leading, spacing: 16) {
                    field("Name", text: $name, isFocused: focusedField == .name) {
                        TextField("Name", text: $name, prompt: Text("Your name").foregroundStyle(Color.kicap.opacity(0.45)))
                            .textContentType(.name)
                            .focused($focusedField, equals: .name)
                            .submitLabel(.next)
                            .onSubmit { focusedField = .email }
                    }

                    field("Email", text: $email, isFocused: focusedField == .email) {
                        TextField("Email", text: $email, prompt: Text("Enter your email").foregroundStyle(Color.kicap.opacity(0.45)))
                            .textContentType(.username)
                            .keyboardType(.emailAddress)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .focused($focusedField, equals: .email)
                            .submitLabel(.next)
                            .onSubmit { focusedField = .password }
                    }

                    VStack(alignment: .leading, spacing: 7) {
                        fieldLabel("Password", isFocused: focusedField == .password)
                        fieldCard {
                            Image(systemName: "lock")
                                .foregroundStyle(Color.kicap.opacity(0.5))
                                .accessibilityHidden(true)
                            Group {
                                if showPassword {
                                    TextField("Password", text: $password, prompt: Text("At least 8 characters").foregroundStyle(Color.kicap.opacity(0.45)))
                                } else {
                                    SecureField("Password", text: $password, prompt: Text("At least 8 characters").foregroundStyle(Color.kicap.opacity(0.45)))
                                }
                            }
                            .textContentType(.newPassword)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .focused($focusedField, equals: .password)
                            .submitLabel(.go)
                            .onSubmit { submitIfReady() }

                            Button(showPassword ? "Hide password" : "Show password",
                                   systemImage: showPassword ? "eye.slash" : "eye") {
                                showPassword.toggle()
                                focusedField = .password
                            }
                            .labelStyle(.iconOnly)
                            .foregroundStyle(Color.kicap.opacity(0.65))
                            .frame(minWidth: 44, minHeight: 44)
                        }
                        .modifier(LoginFieldStyle(isFocused: focusedField == .password, accent: accent))
                    }

                    if let errorMessage {
                        Label(errorMessage, systemImage: "exclamationmark.circle.fill")
                            .font(.system(.subheadline, design: .rounded))
                            .foregroundStyle(accent)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                }
                .disabled(isSubmitting)
                .modifier(ShakeEffect(trigger: reduceMotion ? 0 : shakeTrigger))
                .animation(reduceMotion ? nil : .linear(duration: 0.4), value: shakeTrigger)

                Text(LegalConsent.text(prefix: "By creating an account"))
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap.opacity(0.75))
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
                    .frame(maxWidth: .infinity)

                submitButton

                HStack(spacing: 4) {
                    Text("Already have an account?")
                        .foregroundStyle(Color.kicap.opacity(0.7))
                    Button("Sign in") { dismiss() }
                        .fontWeight(.semibold)
                        .foregroundStyle(accent)
                        .frame(minHeight: 44)
                }
                .font(.system(.subheadline, design: .rounded))
                .frame(maxWidth: .infinity)
            }
            .foregroundStyle(Color.kicap)
            .padding(.horizontal, 28)
            .padding(.bottom, 16)
            .frame(maxWidth: 440)
            .frame(maxWidth: .infinity)
        }
        .scrollDismissesKeyboard(.interactively)
        .background(paper.ignoresSafeArea())
        .preferredColorScheme(.light)
        .tint(accent)
    }

    @ViewBuilder
    private func field<Content: View>(_ label: String, text: Binding<String>, isFocused: Bool, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 7) {
            fieldLabel(label, isFocused: isFocused)
            fieldCard {
                content()
            }
            .modifier(LoginFieldStyle(isFocused: isFocused, accent: accent))
        }
    }

    private func fieldLabel(_ title: String, isFocused: Bool) -> some View {
        Text(title)
            .font(.system(.footnote, design: .rounded, weight: .semibold))
            .foregroundStyle(isFocused ? accent : Color.kicap.opacity(0.75))
    }

    @ViewBuilder
    private func fieldCard<Content: View>(@ViewBuilder content: () -> Content) -> some View {
        HStack(spacing: 12, content: content)
            .font(.system(.body, design: .rounded))
            .padding(.leading, 16)
            .padding(.trailing, 8)
            .padding(.vertical, 4)
            .frame(minHeight: 56)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 16))
    }

    private func submitIfReady() {
        guard !isSubmitting, !name.isEmpty, !email.isEmpty, password.count >= 8 else { return }
        Task { await submit() }
    }

    private var submitButton: some View {
        Button(action: submitIfReady) {
            HStack(spacing: 10) {
                if isSubmitting {
                    ProgressView().tint(.white)
                }
                Text(isSubmitting ? "Creating account…" : "Create account")
                    .contentTransition(.opacity)
                if !isSubmitting {
                    Image(systemName: "arrow.right")
                        .font(.system(.subheadline, weight: .semibold))
                        .accessibilityHidden(true)
                }
            }
            .font(.system(.headline, design: .rounded))
            .frame(maxWidth: .infinity, minHeight: 56)
        }
        .buttonStyle(LoginButtonStyle(accent: accent, isLoading: isSubmitting))
        .animation(reduceMotion ? nil : .easeInOut(duration: 0.2), value: isSubmitting)
        .disabled(isSubmitting || name.isEmpty || email.isEmpty || password.count < 8)
    }

    @MainActor
    private func submit() async {
        focusedField = nil
        errorMessage = nil
        isSubmitting = true
        defer { isSubmitting = false }

        do {
            try await AuthStore.shared.register(name: name, email: email, password: password)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
        } catch APIError.server(let statusCode) where statusCode == 422 {
            errorMessage = "That email's already taken, or the password's too short."
            shakeTrigger += 1
            UINotificationFeedbackGenerator().notificationOccurred(.error)
        } catch {
            errorMessage = "Couldn't create your account. Try again in a bit."
            shakeTrigger += 1
            UINotificationFeedbackGenerator().notificationOccurred(.error)
        }
    }
}

#Preview {
    SignUpView()
}
