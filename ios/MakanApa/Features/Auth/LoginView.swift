import SwiftUI
import UIKit

struct LoginView: View {
    private enum Field {
        case email, password
    }

    @State private var email = ""
    @State private var password = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var shakeTrigger = 0
    @State private var showSignUp = false
    @State private var pendingLink: PendingLink?
    @State private var showPassword = false
    @State private var appeared = false
    @FocusState private var focusedField: Field?

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @ScaledMetric(relativeTo: .largeTitle) private var headlineSize = 32

    private let accent = Color(red: 0.70, green: 0.18, blue: 0.12)
    private let paper = Color(red: 0.99, green: 0.98, blue: 0.96)

    var body: some View {
        GeometryReader { geometry in
            ScrollView {
                VStack(spacing: 0) {
                    brandHeader
                        .padding(.bottom, 16)

                    if focusedField == nil {
                        welcomeHeader(compact: geometry.size.height < 720)
                            .padding(.bottom, 28)
                            .transition(reduceMotion ? .opacity : .opacity.combined(with: .scale(scale: 0.96, anchor: .top)))
                    }

                    loginForm
                }
                .padding(.horizontal, 28)
                .padding(.top, 12)
                .padding(.bottom, 16)
                .frame(maxWidth: 440)
                .frame(maxWidth: .infinity)
                .frame(minHeight: geometry.size.height, alignment: .center)
            }
            .scrollDismissesKeyboard(.interactively)
            .animation(reduceMotion ? nil : .spring(duration: 0.42, bounce: 0), value: focusedField != nil)
        }
        .background(paper.ignoresSafeArea())
        .preferredColorScheme(.light)
        .tint(accent)
        .onAppear {
            withAnimation(reduceMotion ? nil : .easeOut(duration: 0.5)) {
                appeared = true
            }
        }
        .sheet(isPresented: $showSignUp) { SignUpView() }
        .sheet(item: $pendingLink) { link in
            LinkAccountSheet(linkToken: link.linkToken, email: link.email, provider: link.provider)
        }
    }

    private var brandHeader: some View {
        HStack {
            Text("MakanApa?")
                .font(.system(.title3, design: .rounded, weight: .heavy))
                .tracking(-0.6)
                .foregroundStyle(accent)
            Spacer(minLength: 12)
        }
        .padding(.bottom, focusedField == nil ? 0 : 12)
    }

    private func welcomeHeader(compact: Bool) -> some View {
        VStack(spacing: 12) {
            LoginMascotView(size: compact ? 128 : 164)

            VStack(spacing: 8) {
                Text("Less thinking. More makan.")
                    .font(.system(size: headlineSize, weight: .bold, design: .rounded))
                    .tracking(-1.1)
                    .frame(maxWidth: 310)
                    .fixedSize(horizontal: false, vertical: true)
                    .accessibilityAddTraits(.isHeader)
                Text("Good food near campus.\nFor your mood and your budget.")
                    .font(.system(.subheadline, design: .rounded))
                    .foregroundStyle(Color.kicap.opacity(0.65))
                    .lineSpacing(3)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .multilineTextAlignment(.center)
            .opacity(appeared ? 1 : 0)
        }
        .foregroundStyle(Color.kicap)
        .frame(maxWidth: .infinity)
    }

    private var loginForm: some View {
        VStack(alignment: .leading, spacing: 18) {
            VStack(alignment: .leading, spacing: 6) {
                Text("Welcome back")
                    .font(.system(.title3, design: .rounded, weight: .bold))
                    .accessibilityAddTraits(.isHeader)
                Text("Sign in. Your next favourite is waiting.")
                    .font(.system(.subheadline, design: .rounded))
                    .foregroundStyle(Color.kicap.opacity(0.7))
            }

            SocialSignInButtons(
                onNeedsLinking: { linkToken, email, provider in
                    pendingLink = PendingLink(linkToken: linkToken, email: email, provider: provider)
                },
                onError: { message in
                    errorMessage = message
                    shakeTrigger += 1
                }
            )

            HStack(spacing: 10) {
                Rectangle().fill(Color.kicap.opacity(0.12)).frame(height: 1)
                Text("or")
                    .font(.system(.footnote, design: .rounded))
                    .foregroundStyle(Color.kicap.opacity(0.5))
                Rectangle().fill(Color.kicap.opacity(0.12)).frame(height: 1)
            }

            VStack(alignment: .leading, spacing: 16) {
                VStack(alignment: .leading, spacing: 7) {
                    fieldLabel("Email", isFocused: focusedField == .email)
                    fieldCard {
                        Image(systemName: "envelope")
                            .foregroundStyle(Color.kicap.opacity(0.5))
                            .accessibilityHidden(true)
                        TextField("Email", text: $email, prompt: Text("Enter your email").foregroundStyle(Color.kicap.opacity(0.45)))
                            .textContentType(.username)
                            .keyboardType(.emailAddress)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .focused($focusedField, equals: .email)
                            .submitLabel(.next)
                            .onSubmit { focusedField = .password }
                            .accessibilityLabel("Email")
                    }
                    .modifier(LoginFieldStyle(isFocused: focusedField == .email, accent: accent))
                }

                VStack(alignment: .leading, spacing: 7) {
                    fieldLabel("Password", isFocused: focusedField == .password)
                    fieldCard {
                        Image(systemName: "lock")
                            .foregroundStyle(Color.kicap.opacity(0.5))
                            .accessibilityHidden(true)
                        Group {
                            if showPassword {
                                TextField("Password", text: $password, prompt: Text("Enter your password").foregroundStyle(Color.kicap.opacity(0.45)))
                            } else {
                                SecureField("Password", text: $password, prompt: Text("Enter your password").foregroundStyle(Color.kicap.opacity(0.45)))
                            }
                        }
                        .textContentType(.password)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .focused($focusedField, equals: .password)
                        .submitLabel(.go)
                        .onSubmit { submitIfReady() }
                        .accessibilityLabel("Password")

                        Button(showPassword ? "Hide password" : "Show password",
                               systemImage: showPassword ? "eye.slash" : "eye") {
                            showPassword.toggle()
                            focusedField = .password
                        }
                        .labelStyle(.iconOnly)
                        .foregroundStyle(Color.kicap.opacity(0.65))
                        .contentTransition(reduceMotion ? .identity : .symbolEffect(.replace))
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

            signInButton

            HStack(spacing: 4) {
                Text("New here?")
                    .foregroundStyle(Color.kicap.opacity(0.7))
                Button("Sign up") { showSignUp = true }
                    .fontWeight(.semibold)
                    .foregroundStyle(accent)
                    .frame(minHeight: 44)
            }
            .font(.system(.subheadline, design: .rounded))
            .frame(maxWidth: .infinity)
        }
        .foregroundStyle(Color.kicap)
        .opacity(appeared ? 1 : 0)
    }

    private func fieldLabel(_ title: String, isFocused: Bool) -> some View {
        Text(title)
            .font(.system(.footnote, design: .rounded, weight: .semibold))
            .foregroundStyle(isFocused ? accent : Color.kicap.opacity(0.75))
    }

    private func submitIfReady() {
        guard !isSubmitting, !email.isEmpty, !password.isEmpty else { return }
        Task { await submit() }
    }

    private var signInButton: some View {
        Button(action: submitIfReady) {
            HStack(spacing: 10) {
                if isSubmitting {
                    ProgressView().tint(.white)
                }
                Text(isSubmitting ? "Signing in…" : Copy.signIn)
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
        .disabled(isSubmitting || email.isEmpty || password.isEmpty)
    }

    @MainActor
    private func submit() async {
        focusedField = nil
        errorMessage = nil
        isSubmitting = true
        defer { isSubmitting = false }

        do {
            try await AuthStore.shared.login(email: email, password: password)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
        } catch {
            errorMessage = Copy.loginInvalidCredentials
            shakeTrigger += 1
            UINotificationFeedbackGenerator().notificationOccurred(.error)
        }
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
}

/// Small ±5pt horizontal shake on failed login — the form card only, not the whole screen.
/// Internal, not private — SignUpView reuses this for its own failed-submit feedback.
struct ShakeEffect: ViewModifier {
    let trigger: Int

    func body(content: Content) -> some View {
        content
            .modifier(ShakeGeometryEffect(animatableData: CGFloat(trigger)))
    }
}

struct ShakeGeometryEffect: GeometryEffect {
    var animatableData: CGFloat

    func effectValue(size: CGSize) -> ProjectionTransform {
        let shakes: CGFloat = 3
        let amplitude: CGFloat = 5
        let progress = animatableData.truncatingRemainder(dividingBy: 1)
        let translation = amplitude * sin(progress * .pi * shakes * 2) * (1 - progress)
        return ProjectionTransform(CGAffineTransform(translationX: animatableData == 0 ? 0 : translation, y: 0))
    }
}

private struct PendingLink: Identifiable {
    let linkToken: String
    let email: String
    let provider: String
    var id: String { linkToken }
}

#Preview {
    LoginView()
}
