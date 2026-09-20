import SwiftUI

/// Small tag for a restaurant's community-derived vibe (e.g. "Student budget", "Hidden gem").
/// Shares `CommunityBadge`'s capsule visual language via `capsuleTagStyle()` but stays a
/// separate type — a vibe tag and a community affiliation are different concepts.
struct VibeBadge: View {
    let vibe: String

    private var label: String {
        switch vibe {
        case "chill": return "☕ Chill"
        case "study": return "📚 Study"
        case "student_budget": return "💸 Student budget"
        case "hidden_gem": return "✨ Hidden gem"
        case "date": return "💕 Date"
        case "lepak": return "🛋️ Lepak"
        case "family": return "👨‍👩‍👧 Family"
        case "late_night": return "🌙 Late night"
        default: return vibe.capitalized
        }
    }

    var body: some View {
        Text(label)
            .capsuleTagStyle(background: Color.pandan.opacity(0.2))
            .accessibilityLabel("Vibe, \(label)")
    }
}
