import SwiftUI

/// Inline, dismissible "save this to an account" card for a guest — a card in the page, never a
/// sheet, so it can't interrupt a pick. `GuestUpgradeNudge` decides when it's eligible.
struct GuestUpgradeCard: View {
    let reason: GuestUpgradeNudge.Reason

    private var nudge = GuestUpgradeNudge.shared

    init(reason: GuestUpgradeNudge.Reason) {
        self.reason = reason
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.makanDisplay(17))
                    .foregroundStyle(Color.kicap)
                    .fixedSize(horizontal: false, vertical: true)
                    .accessibilityAddTraits(.isHeader)
                Text(detail)
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                    .fixedSize(horizontal: false, vertical: true)
                Text(Copy.guestCarryOver)
                    .font(.makanBody(12).weight(.semibold))
                    .foregroundStyle(Color.pandan)
                    .fixedSize(horizontal: false, vertical: true)
            }

            QuickSignInPanel(source: reason.signupSource)

            Button("Not now") {
                withAnimation(Motion.standard) { nudge.dismiss(reason) }
            }
            .font(.makanBody(14))
            .foregroundStyle(.secondary)
            .frame(maxWidth: .infinity, minHeight: 44)
        }
        .padding(16)
        .background(Color.white, in: RoundedRectangle(cornerRadius: 24))
        .shadow(color: Color.kicap.opacity(0.06), radius: 10, y: 4)
    }

    private var title: String {
        switch reason {
        case .picks: Copy.nudgePicksTitle
        case .saves: Copy.nudgeSavesTitle
        }
    }

    private var detail: String {
        switch reason {
        case .picks: Copy.nudgePicksDetail
        case .saves: Copy.nudgeSavesDetail
        }
    }
}
