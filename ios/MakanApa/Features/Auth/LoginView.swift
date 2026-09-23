import SwiftUI
import UIKit

/// Sign-in in two beats on one screen. The landing leads with the brand (sunburst hero, food
/// polaroids, dish ribbons) and the three ways in: Apple, Google, email. Choosing email swaps in
/// a focused form whose header the hero mascot morphs into, so the fields sit high above the
/// keyboard instead of below a tall hero the way the old single long form did.
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
    @State private var showEmailForm = false
    @State private var appeared = false
    @State private var headlineProgress = 0.0
    @State private var sheenTrigger = 0
    @FocusState private var focusedField: Field?
    @Namespace private var mascotSpace

    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private let accent = Color(red: 0.70, green: 0.18, blue: 0.12)
    private let paper = Color(red: 0.99, green: 0.98, blue: 0.96)

    private static let geng: [AvatarCharacter] = [.nasi, .roti, .laksa, .tehTarik]

    var body: some View {
        GeometryReader { geometry in
            ScrollView {
                // A ZStack, not a VStack, so the outgoing and incoming modes overlap mid-transition
                // instead of stacking and shoving each other down.
                ZStack(alignment: .top) {
                    if showEmailForm {
                        emailForm
                            .transition(modeTransition)
                    } else {
                        landing(height: geometry.size.height)
                            .transition(modeTransition)
                    }
                }
                .frame(maxWidth: .infinity, minHeight: geometry.size.height, alignment: .top)
            }
            .scrollDismissesKeyboard(.interactively)
            .scrollBounceBehavior(.basedOnSize)
        }
        .background(paper.ignoresSafeArea())
        .preferredColorScheme(.light)
        .tint(accent)
        .onAppear { appeared = true }
        .sheet(isPresented: $showSignUp) { SignUpView() }
        .sheet(item: $pendingLink) { link in
            LinkAccountSheet(linkToken: link.linkToken, email: link.email, provider: link.provider)
        }
    }

    // MARK: - Landing

    private func landing(height: CGFloat) -> some View {
        VStack(spacing: 0) {
            LoginHeroView(height: min(max(height * 0.31, 200), 280), mascotNamespace: mascotSpace)

            VStack(spacing: 16) {
                headline
                communityPill
                    .rise(appeared, delay: 0.55, reduceMotion: reduceMotion)
            }
            .padding(.top, 10)
            .padding(.horizontal, 24)

            Spacer(minLength: 24)

            VStack(spacing: 12) {
                SocialSignInButtons(
                    onNeedsLinking: { linkToken, email, provider in
                        pendingLink = PendingLink(linkToken: linkToken, email: email, provider: provider)
                    },
                    onError: { message in
                        errorMessage = message
                        shakeTrigger += 1
                    }
                )
                .rise(appeared, delay: 0.65, reduceMotion: reduceMotion)

                emailChoiceButton
                    .rise(appeared, delay: 0.72, reduceMotion: reduceMotion)

                if let errorMessage {
                    errorLabel(errorMessage)
                }

                createAccountRow
                    .rise(appeared, delay: 0.8, reduceMotion: reduceMotion)

                legalNote
                    .rise(appeared, delay: 0.85, reduceMotion: reduceMotion)
            }
            .modifier(ShakeEffect(trigger: reduceMotion ? 0 : shakeTrigger))
            .animation(reduceMotion ? nil : .linear(duration: 0.4), value: shakeTrigger)
            .padding(.horizontal, 24)
            .padding(.bottom, 12)
            .frame(maxWidth: 440)
        }
        .frame(minHeight: height)
    }

    private var headline: some View {
        VStack(spacing: 8) {
            Text("Less thinking.\nMore \(Text("makan.").foregroundStyle(Color.sambalRed))")
                .font(.makanDisplay(34))
                .tracking(-1)
                .textRenderer(GlyphRevealRenderer(progress: headlineProgress))
                .foregroundStyle(Color.kicap)
                .accessibilityAddTraits(.isHeader)

            Text("Good food near campus.\nFor your mood and your budget.")
                .font(.makanBody(15))
                .foregroundStyle(Color.kicap.opacity(0.65))
                .lineSpacing(2)
                .rise(appeared, delay: 0.45, reduceMotion: reduceMotion)
        }
        .multilineTextAlignment(.center)
        .fixedSize(horizontal: false, vertical: true)
        // Replays each time the landing comes back from the email form.
        .onAppear {
            if reduceMotion {
                headlineProgress = 1
            } else {
                withAnimation(.easeOut(duration: 1.3).delay(0.25)) { headlineProgress = 1 }
            }
        }
        .onDisappear { headlineProgress = 0 }
    }

    private var communityPill: some View {
        HStack(spacing: 10) {
            HStack(spacing: -9) {
                ForEach(Array(Self.geng.enumerated()), id: \.element) { index, character in
                    Image(character.imageName)
                        .resizable()
                        .scaledToFit()
                        .padding(3)
                        .frame(width: 30, height: 30)
                        .background(Color.nasiCream, in: Circle())
                        .overlay(Circle().stroke(.white, lineWidth: 2))
                        .scaleEffect(appeared || reduceMotion ? 1 : 0.3)
                        .animation(reduceMotion ? nil : .spring(duration: 0.5, bounce: 0.5).delay(0.7 + Double(index) * 0.07),
                                   value: appeared)
                }
            }
            .accessibilityHidden(true)

            Text("Join the makan geng near you")
                .font(.makanBody(13))
                .foregroundStyle(Color.kicap.opacity(0.75))
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(.leading, 6)
        .padding(.trailing, 14)
        .padding(.vertical, 6)
        .background(.white, in: RoundedRectangle(cornerRadius: 21))
        .overlay(RoundedRectangle(cornerRadius: 21).strokeBorder(Color.kicap.opacity(0.08)))
        .shadow(color: Color.kicap.opacity(0.05), radius: 8, y: 3)
    }

    private var emailChoiceButton: some View {
        Button(action: openEmailForm) {
            HStack(spacing: 10) {
                Image(systemName: "envelope.fill")
                    .font(.system(size: 16, weight: .semibold))
                    .accessibilityHidden(true)
                Text("Continue with email")
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
    }

    private var createAccountRow: some View {
        Button {
            showSignUp = true
        } label: {
            HStack(spacing: 4) {
                Text("New to MakanApa?")
                    .foregroundStyle(Color.kicap.opacity(0.7))
                Text("Create an account")
                    .fontWeight(.bold)
                    .foregroundStyle(accent)
            }
            .font(.makanBody(15))
            .frame(maxWidth: .infinity, minHeight: 44)
            .contentShape(Rectangle())
        }
        .buttonStyle(PressCompressStyle())
    }

    private var legalNote: some View {
        Text(legalText)
            .font(.makanBody(12))
            .foregroundStyle(Color.kicap.opacity(0.5))
            .multilineTextAlignment(.center)
    }

    private var legalText: AttributedString {
        var text = AttributedString("By continuing, you acknowledge our Privacy Policy.")
        if let range = text.range(of: "Privacy Policy"), let url = URL(string: Copy.privacyPolicyURL) {
            text[range].link = url
            text[range].underlineStyle = .single
        }
        return text
    }

    // MARK: - Email form

    private var emailForm: some View {
        VStack(alignment: .leading, spacing: 18) {
            CircleBackButton(action: closeEmailForm)
                .accessibilityLabel("Other ways to sign in")

            VStack(spacing: 10) {
                LoginMascotView(width: 150)
                    .matchedGeometryEffect(id: "mascot", in: mascotSpace)
                VStack(spacing: 6) {
                    Text("Sign in with email")
                        .font(.makanDisplay(26))
                        .accessibilityAddTraits(.isHeader)
                    Text("Welcome back. Your next favourite is waiting.")
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap.opacity(0.65))
                        .fixedSize(horizontal: false, vertical: true)
                }
                .multilineTextAlignment(.center)
            }
            .frame(maxWidth: .infinity)
            .padding(.bottom, 4)

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
                    errorLabel(errorMessage)
                }
            }
            .disabled(isSubmitting)
            .modifier(ShakeEffect(trigger: reduceMotion ? 0 : shakeTrigger))
            .animation(reduceMotion ? nil : .linear(duration: 0.4), value: shakeTrigger)

            signInButton

            signUpButton
        }
        .foregroundStyle(Color.kicap)
        .padding(.horizontal, 24)
        .padding(.top, 8)
        .padding(.bottom, 16)
        .frame(maxWidth: 440)
    }

    private func fieldLabel(_ title: String, isFocused: Bool) -> some View {
        Text(title)
            .font(.system(.footnote, design: .rounded, weight: .semibold))
            .foregroundStyle(isFocused ? accent : Color.kicap.opacity(0.75))
    }

    private func errorLabel(_ message: String) -> some View {
        Label(message, systemImage: "exclamationmark.circle.fill")
            .font(.system(.subheadline, design: .rounded))
            .foregroundStyle(accent)
            .fixedSize(horizontal: false, vertical: true)
    }

    private var canSubmit: Bool {
        !isSubmitting && !email.isEmpty && !password.isEmpty
    }

    private func submitIfReady() {
        guard canSubmit else { return }
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
        .overlay { SheenSweep(trigger: sheenTrigger, cornerRadius: 16) }
        .animation(reduceMotion ? nil : .easeInOut(duration: 0.2), value: isSubmitting)
        .disabled(!canSubmit)
        // One light sweep the moment both fields are filled — "you're good to go".
        .onChange(of: !email.isEmpty && !password.isEmpty) { _, ready in
            if ready && !reduceMotion { sheenTrigger += 1 }
        }
    }

    // A real secondary button, not a small text link — a quick "New here? Sign up" line was
    // easy to miss below the primary sign-in button, so this gives new-account creation equal
    // visual weight to signing in instead of reading as fine print.
    private var signUpButton: some View {
        Button {
            showSignUp = true
        } label: {
            Text("Create an account")
                .font(.system(.headline, design: .rounded, weight: .semibold))
                .frame(maxWidth: .infinity, minHeight: 56)
        }
        .foregroundStyle(accent)
        .background(Color.clear, in: RoundedRectangle(cornerRadius: 16))
        .overlay {
            RoundedRectangle(cornerRadius: 16)
                .strokeBorder(accent, lineWidth: 1.5)
        }
        .disabled(isSubmitting)
    }

    // MARK: - Mode switching

    private var modeAnimation: Animation {
        reduceMotion ? .easeOut(duration: 0.2) : .spring(duration: 0.55, bounce: 0.15)
    }

    private var modeTransition: AnyTransition {
        reduceMotion ? .opacity : AnyTransition(BlurRise())
    }

    private func openEmailForm() {
        errorMessage = nil
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        withAnimation(modeAnimation) { showEmailForm = true }
        // Focus once the morph has settled, so the keyboard doesn't rise mid-transition.
        Task { @MainActor in
            try? await Task.sleep(for: .milliseconds(450))
            if showEmailForm, focusedField == nil { focusedField = .email }
        }
    }

    private func closeEmailForm() {
        focusedField = nil
        errorMessage = nil
        withAnimation(modeAnimation) { showEmailForm = false }
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

/// Mode switch: fades in out of a blur while rising; leaves by drifting up into one.
private struct BlurRise: Transition {
    func body(content: Content, phase: TransitionPhase) -> some View {
        content
            .opacity(phase.isIdentity ? 1 : 0)
            .blur(radius: phase.isIdentity ? 0 : 10)
            .offset(y: phase == .willAppear ? 24 : phase == .didDisappear ? -12 : 0)
    }
}

/// One diagonal highlight passing across a button each time `trigger` changes.
private struct SheenSweep: View {
    let trigger: Int
    let cornerRadius: CGFloat

    var body: some View {
        GeometryReader { proxy in
            LinearGradient(colors: [.clear, .white.opacity(0.45), .clear], startPoint: .leading, endPoint: .trailing)
                .frame(width: proxy.size.width * 0.3)
                .rotationEffect(.degrees(18))
                .keyframeAnimator(initialValue: -0.4, trigger: trigger) { content, phase in
                    content.offset(x: phase * proxy.size.width)
                } keyframes: { _ in
                    CubicKeyframe(-0.4, duration: 0.05)
                    CubicKeyframe(1.1, duration: 0.75)
                }
        }
        .clipShape(RoundedRectangle(cornerRadius: cornerRadius))
        .allowsHitTesting(false)
        .accessibilityHidden(true)
    }
}

/// Staggered first-appearance: fades up a beat after `delay`. Instant under Reduce Motion.
private struct Rise: ViewModifier {
    let visible: Bool
    let delay: Double
    let reduceMotion: Bool

    func body(content: Content) -> some View {
        content
            .opacity(visible ? 1 : 0)
            .offset(y: visible || reduceMotion ? 0 : 18)
            .animation(reduceMotion ? .easeOut(duration: 0.2) : .spring(duration: 0.6, bounce: 0.2).delay(delay), value: visible)
    }
}

private extension View {
    func rise(_ visible: Bool, delay: Double, reduceMotion: Bool) -> some View {
        modifier(Rise(visible: visible, delay: delay, reduceMotion: reduceMotion))
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
