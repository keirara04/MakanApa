import SwiftUI

struct AmbassadorCardIdentity: View {
    let name: String
    let format: AmbassadorCardFormat

    var body: some View {
        Text(name)
            .font(.system(size: format == .story ? 34 : 28, weight: .heavy, design: .rounded))
            .tracking(-0.7)
            .foregroundStyle(Color.kicap)
            .lineLimit(2)
            .minimumScaleFactor(0.65)
            .multilineTextAlignment(.center)
            .frame(maxWidth: .infinity)
    }
}
