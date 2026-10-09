import SwiftUI

/// Shared capsule visual language for small text-only tags (community affiliation, vibe) —
/// same corner radius/padding/font/background token so they read as one design system, even
/// though `CommunityBadge` and `VibeBadge` stay semantically separate types (a vibe isn't a
/// community).
struct CapsuleTagStyle: ViewModifier {
    var background: Color

    func body(content: Content) -> some View {
        content
            .font(.makanBody(11))
            .fontWeight(.semibold)
            .foregroundStyle(Color.kicap)
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(background)
            .clipShape(Capsule())
    }
}

extension View {
    func capsuleTagStyle(background: Color = Color.kunyit.opacity(0.25)) -> some View {
        modifier(CapsuleTagStyle(background: background))
    }
}

/// Compact identity marker for a user's MakanApa community ("UKM", "UM", "Public"). Deliberately
/// text-only and admin-agnostic — reused later wherever Community needs to attribute content to
/// a person (e.g. "Aiman · UKM"), not just in the admin panel.
struct CommunityBadge: View {
    let label: String

    init(affiliationType: String?, university: String?, area: String? = nil) {
        switch affiliationType {
        case "university":
            label = university ?? "Public"
        case "area":
            label = area ?? "Public"
        case "public":
            label = "Public"
        default:
            label = "Not assigned"
        }
    }

    var body: some View {
        Text(label)
            .capsuleTagStyle()
            .accessibilityLabel("Community, \(label)")
    }
}

/// "★ Ambassador · UKM" — next to an author's name, and in their own profile. Text, not the crest
/// artwork: the crest turns into a blob below ~48pt, this stays legible at any size. Gold star to
/// echo the crest's trim.
struct AmbassadorBadge: View {
    let role: AmbassadorRole

    var body: some View {
        HStack(spacing: 3) {
            Image(systemName: "star.fill")
                .foregroundStyle(Color.kunyit)
            Text(Copy.ambassadorBadge(role.name))
                .foregroundStyle(Color.kicap)
                .lineLimit(1)
        }
        .font(.caption2.weight(.semibold))
        .padding(.horizontal, 7)
        .padding(.vertical, 3)
        .background(Color.surface, in: Capsule())
        .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
        .fixedSize()
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(Copy.ambassadorBadgeAccessibility(role.name))
    }
}
