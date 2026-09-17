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
    @State private var showJoinBeta = false
    @State private var appeared = false
    @FocusState private var focusedField: Field?

    var body: some View {
        VStack(spacing: 28) {
            Spacer(minLength: 40)

            VStack(spacing: 10) {
                Text(Copy.privateBetaEyebrow)
                    .font(.makanBody(11))
                    .tracking(1.2)
                    .foregroundStyle(Color.sambalRed)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 4)
                    .background(Color.sambalRed.opacity(0.1))
                    .clipShape(Capsule())

                (Text("Makan").foregroundStyle(Color.kicap) + Text("Apa?").foregroundStyle(Color.sambalRed))
                    .font(.makanDisplay(32))

                Text(Copy.loginTagline)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
            }
            .opacity(appeared ? 1 : 0)
            .offset(y: appeared ? 0 : 8)

            VStack(spacing: 14) {
                fieldCard {
                    TextField("Email", text: $email)
                        .textContentType(.emailAddress)
                        .keyboardType(.emailAddress)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .focused($focusedField, equals: .email)
                }
                .borderColor(focusedField == .email ? Color.sambalRed : Color.kicap.opacity(0.12))

                fieldCard {
                    SecureField("Password", text: $password)
                        .textContentType(.password)
                        .focused($focusedField, equals: .password)
                }
                .borderColor(focusedField == .password ? Color.sambalRed : Color.kicap.opacity(0.12))

                if let errorMessage {
                    Text(errorMessage)
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .frame(maxWidth: .infinity, alignment: .leading)
                }

                signInButton
            }
            .modifier(ShakeEffect(trigger: shakeTrigger))
            .animation(.linear(duration: 0.4), value: shakeTrigger)
            .opacity(appeared ? 1 : 0)
            .offset(y: appeared ? 0 : 10)

            VStack(spacing: 4) {
                Text(Copy.joinBetaPrompt)
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                Button(Copy.joinBetaCTA) {
                    showJoinBeta = true
                }
                .font(.makanBody(13))
                .foregroundStyle(Color.sambalRed)
            }
            .opacity(appeared ? 1 : 0)

            Spacer(minLength: 24)
        }
        .padding(.horizontal, 24)
        .frame(maxHeight: .infinity)
        .background(Color.nasiCream)
        .onAppear {
            withAnimation(Motion.standard.delay(0.05)) { appeared = true }
        }
        .sheet(isPresented: $showJoinBeta) {
            joinBetaSheet
        }
    }

    private var signInButton: some View {
        Button {
            Task { await submit() }
        } label: {
            ZStack {
                Text(Copy.signIn)
                    .opacity(isSubmitting ? 0 : 1)
                ProgressView()
                    .tint(.white)
                    .opacity(isSubmitting ? 1 : 0)
            }
            .font(.makanDisplay(18))
            .foregroundStyle(.white)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 16)
        }
        .background(Color.sambalRed)
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .scaleEffect(isSubmitting ? 0.97 : 1.0)
        .animation(Motion.quick, value: isSubmitting)
        .disabled(isSubmitting || email.isEmpty || password.isEmpty)
        .opacity((email.isEmpty || password.isEmpty) ? 0.6 : 1)
    }

    private var joinBetaSheet: some View {
        VStack(spacing: 20) {
            Text(Copy.joinBetaSheetTitle)
                .font(.makanDisplay(22))
                .foregroundStyle(Color.kicap)

            Text(Copy.joinBetaSheetBody)
                .font(.makanBody(15))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)

            MakanPrimaryButton(title: Copy.joinBetaGotIt) {
                showJoinBeta = false
            }
        }
        .padding(28)
        .presentationDetents([.medium])
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
        content()
            .font(.makanBody(16))
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 16))
    }
}

private extension View {
    func borderColor(_ color: Color) -> some View {
        overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(color, lineWidth: 1.5)
        )
        .animation(.easeOut(duration: 0.17), value: color)
    }
}

/// Small ±5pt horizontal shake on failed login — the form card only, not the whole screen.
private struct ShakeEffect: ViewModifier {
    let trigger: Int

    func body(content: Content) -> some View {
        content
            .modifier(ShakeGeometryEffect(animatableData: CGFloat(trigger)))
    }
}

private struct ShakeGeometryEffect: GeometryEffect {
    var animatableData: CGFloat

    func effectValue(size: CGSize) -> ProjectionTransform {
        let shakes: CGFloat = 3
        let amplitude: CGFloat = 5
        let progress = animatableData.truncatingRemainder(dividingBy: 1)
        let translation = amplitude * sin(progress * .pi * shakes * 2) * (1 - progress)
        return ProjectionTransform(CGAffineTransform(translationX: animatableData == 0 ? 0 : translation, y: 0))
    }
}

#Preview {
    LoginView()
}
