import SwiftUI

struct PreferenceStepHeading: View {
    @ScaledMetric(relativeTo: .largeTitle) private var titleSize = 36

    let title: String
    let subtitle: String

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(title)
                .font(.system(size: titleSize, weight: .bold, design: .rounded))
                .tracking(-1.2)
                .foregroundStyle(Color.kicap)
                .fixedSize(horizontal: false, vertical: true)
                .accessibilityAddTraits(.isHeader)
            Text(subtitle)
                .font(.subheadline)
                .foregroundStyle(Color.kicap.opacity(0.65))
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(.top, 4)
    }
}
