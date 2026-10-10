import SwiftUI

/// Shown once when an admin makes someone an ambassador (and again only if they're moved to a
/// different community). The rare, earned moment — so it's allowed the crest at full size and a
/// playful entrance. The crest artwork says "UNI", so area ambassadors get Bubu with a gold star.
struct AmbassadorWelcomeView: View {
    let role: AmbassadorRole
    let onDone: () -> Void

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var shown = false

    var body: some View {
        VStack(spacing: 32) {
            Spacer(minLength: 0)

            crest
                .scaleEffect(shown || reduceMotion ? 1 : 0.6)
                .rotationEffect(.degrees(shown || reduceMotion ? 0 : -8))
                .opacity(shown ? 1 : 0)
                .animation(reduceMotion ? .easeOut(duration: 0.2) : Motion.playful, value: shown)
                .accessibilityHidden(true)

            VStack(spacing: 12) {
                Text(Copy.ambassadorWelcomeTitle)
                    .font(.largeTitle.weight(.bold))
                    .foregroundStyle(Color.kicap)
                    .accessibilityAddTraits(.isHeader)
                Text(Copy.ambassadorWelcomeBody(role.name))
                    .font(.body)
                    .foregroundStyle(Color.kicapSecondary)
                AmbassadorBadge(role: role)
                    .scaleEffect(1.25)
                    .padding(.top, 8)
            }
            .multilineTextAlignment(.center)
            .fixedSize(horizontal: false, vertical: true)
            .opacity(shown ? 1 : 0)
            .offset(y: shown || reduceMotion ? 0 : 12)
            .animation((reduceMotion ? .easeOut(duration: 0.2) : Motion.standard).delay(0.2), value: shown)

            Spacer(minLength: 0)

            MakanPrimaryButton(title: Copy.ambassadorWelcomeCTA, action: onDone)
        }
        .padding(.horizontal, 32)
        .padding(.vertical, 24)
        .frame(maxWidth: 540)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color.nasiCream.ignoresSafeArea())
        .sensoryFeedback(.success, trigger: shown) { _, isShown in isShown }
        .onAppear { shown = true }
    }

    @ViewBuilder
    private var crest: some View {
        if role.type == "university" {
            Image("AmbassadorCrest")
                .resizable()
                .scaledToFit()
                .frame(maxWidth: 260)
        } else {
            // The celebrate art has the wordmark and a cap slogan baked in — plain Bubu + a gold
            // star instead.
            MascotView(mood: .idle, size: 160)
                .overlay(alignment: .topTrailing) {
                    Image(systemName: "star.fill")
                        .font(.largeTitle)
                        .foregroundStyle(Color.kunyit)
                        .offset(x: 8, y: -4)
                }
        }
    }
}

#Preview {
    AmbassadorWelcomeView(role: AmbassadorRole(type: "university", name: "UKM"), onDone: {})
}
