import SwiftUI

/// A fixed-size export canvas; the surrounding sheet retains Dynamic Type and scales the preview.
struct AmbassadorShareCard: View {
    let role: AmbassadorRole
    let name: String
    var format: AmbassadorCardFormat = .story

    private var isStory: Bool { format == .story }

    var body: some View {
        VStack(spacing: 0) {
            Spacer(minLength: isStory ? 24 : 10)

            VStack(spacing: isStory ? 12 : 10) {
                AmbassadorCardArtwork(isUniversity: role.type == "university", format: format)
                    .frame(height: isStory ? 320 : 232)

                AmbassadorCardIdentity(name: name, format: format)
                    .frame(height: isStory ? 58 : 44)

                Label {
                    Text(role.name)
                        .font(.system(size: 14, weight: .bold, design: .rounded))
                        .lineLimit(2)
                        .minimumScaleFactor(0.8)
                        .fixedSize(horizontal: false, vertical: true)
                } icon: {
                    Image(systemName: role.type == "university" ? "graduationcap.fill" : "mappin.circle.fill")
                        .font(.system(size: 16, weight: .bold))
                }
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 18)
                .padding(.vertical, 6)
                .background(Color.surface, in: Capsule())
                .overlay(Capsule().strokeBorder(Color.kunyit, lineWidth: 1.25))
                .frame(height: 48)
            }

            Spacer(minLength: isStory ? 24 : 10)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, isStory ? 24 : 16)
        .frame(width: format.size.width - 24, height: format.height - 24)
        .background {
            AmbassadorCardPaper()
        }
        .clipShape(.rect(cornerRadius: 24))
        .overlay {
            RoundedRectangle(cornerRadius: 18)
                .inset(by: 8)
                .strokeBorder(Color.kunyit.opacity(0.65), lineWidth: 1)
        }
        .padding(12)
        .frame(width: format.size.width, height: format.height)
        .background(Color.sambalRed)
        .environment(\.dynamicTypeSize, .large)
        .environment(\.colorScheme, .light)
    }
}

#Preview("Story") {
    AmbassadorShareCard(role: AmbassadorRole(type: "university", name: "KU"), name: "Keira")
}

#Preview("Post · long names") {
    AmbassadorShareCard(
        role: AmbassadorRole(type: "university", name: "International Islamic University Malaysia"),
        name: "Nur Aisyah Amirah", format: .post
    )
}
