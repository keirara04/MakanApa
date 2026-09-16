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
                .frame(maxWidth: .infinity)
                .padding(.vertical, 18)
        }
        .background(Color.sambalRed)
        .clipShape(RoundedRectangle(cornerRadius: 20))
    }
}
