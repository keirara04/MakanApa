import SwiftUI
import UIKit

/// Full-width sambal-red CTA style shared by "MAKANAPA?" and "JOM MAKAN".
struct MakanPrimaryButton: View {
    let title: String
    let action: () -> Void

    var body: some View {
        Button {
            UIImpactFeedbackGenerator(style: .medium).impactOccurred()
            action()
        } label: {
            Text(title)
                .font(.makanDisplay(20))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity, minHeight: 56)
        }
        // Same 56pt height / 18pt corners as PreferenceActionFooter, so the Decide flow's
        // primary actions ("Continue" → "Find my makan" → "JOM MAKAN") read as one family.
        .background(Color.sambalRed, in: RoundedRectangle(cornerRadius: 18))
        .buttonStyle(PressCompressStyle())
    }
}
