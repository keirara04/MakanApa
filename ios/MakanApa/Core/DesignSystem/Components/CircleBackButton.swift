import SwiftUI

/// The one back affordance for pushed screens (preferences, result, location permission):
/// a 44pt soft circle, so "back" looks the same wherever you are in the Decide flow.
struct CircleBackButton: View {
    let action: () -> Void

    var body: some View {
        Button("Back", systemImage: "chevron.left", action: action)
            .labelStyle(.iconOnly)
            .font(.body.weight(.semibold))
            .foregroundStyle(Color.kicap)
            .frame(width: 44, height: 44)
            .background(.white.opacity(0.6), in: Circle())
    }
}
