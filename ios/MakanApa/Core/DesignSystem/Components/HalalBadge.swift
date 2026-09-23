import SwiftUI

/// Draws the server's halal `display` verbatim — never derives wording from status on-device
/// (backend `HalalPresenter` owns the semantics: only certified says "Halal", expired certs
/// degrade, etc.). Only colour/icon come from `tone`.
struct HalalBadge: View {
    enum Size { case compact, regular }

    let display: HalalDisplay
    var size: Size = .compact
    /// Tapped when the display invites a report (unknown / expired).
    var onReport: (() -> Void)? = nil

    var body: some View {
        if display.invitesReport, let onReport {
            Button(action: onReport) { label }
                .buttonStyle(.plain)
                .accessibilityHint("Opens the halal report form")
        } else {
            label
        }
    }

    private var label: some View {
        HStack(spacing: 4) {
            Image(systemName: icon)
                .font(.system(size: size == .compact ? 10 : 12, weight: .semibold))
            Text(size == .compact ? display.shortLabel : display.longLabel)
                .font(.makanBody(size == .compact ? 11 : 13))
                .lineLimit(1)
        }
        .foregroundStyle(foreground)
        .padding(.horizontal, size == .compact ? 8 : 10)
        .padding(.vertical, size == .compact ? 4 : 6)
        .background(background, in: Capsule())
        .accessibilityElement(children: .combine)
        .accessibilityLabel(display.longLabel)
    }

    private var icon: String {
        switch display.tone {
        case "certified": "checkmark.seal.fill"
        case "friendly": "leaf.fill"
        case "non_halal": "xmark.circle.fill"
        case "warning": "exclamationmark.triangle.fill"
        default: "questionmark.circle"
        }
    }

    private var foreground: Color {
        switch display.tone {
        case "certified", "friendly": Color.pandan
        case "non_halal": Color.sambalRed
        case "warning": Color.kicap
        default: Color.kicap.opacity(0.7)
        }
    }

    private var background: Color {
        switch display.tone {
        case "certified": Color.pandan.opacity(0.16)
        case "friendly": Color.pandan.opacity(0.10)
        case "non_halal": Color.sambalRed.opacity(0.10)
        case "warning": Color.kunyit.opacity(0.25)
        default: Color.kicap.opacity(0.06)
        }
    }
}
