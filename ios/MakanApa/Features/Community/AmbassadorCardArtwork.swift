import SwiftUI

struct AmbassadorCardArtwork: View {
    let isUniversity: Bool
    let format: AmbassadorCardFormat

    var body: some View {
        ZStack {
            Circle()
                .fill(RadialGradient(
                    stops: [
                        .init(color: Color.kunyit.opacity(0.48), location: 0),
                        .init(color: Color.kunyit.opacity(0.22), location: 0.5),
                        .init(color: Color.kunyit.opacity(0), location: 1)
                    ],
                    center: .center,
                    startRadius: 0,
                    endRadius: format == .story ? 174 : 134
                ))
                .scaleEffect(1.18)
                .blur(radius: 8)

            if isUniversity {
                Image("AmbassadorCrest")
                    .resizable()
                    .scaledToFit()
                    .padding(2)
                    .shadow(color: Color.kicap.opacity(0.12), radius: 6, y: 4)
            } else {
                Image("MascotDefault")
                    .resizable()
                    .scaledToFit()
                    .padding(12)
                    .overlay(alignment: .topTrailing) {
                        Image(systemName: "star.circle.fill")
                            .symbolRenderingMode(.palette)
                            .foregroundStyle(Color.kicap, Color.kunyit)
                            .font(.system(size: format == .story ? 50 : 36))
                            .padding(12)
                    }
            }
        }
        .frame(width: format == .story ? 304 : 232)
        .accessibilityHidden(true)
    }
}
