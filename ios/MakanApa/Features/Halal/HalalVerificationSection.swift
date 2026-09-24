import SwiftUI

/// "Halal verification" block on detail sheets: current status, provenance line, the user's own
/// vouch (and where it is in review), approved evidence, and entry points to vouch / history /
/// owner claim. A vouch never changes the status by itself — an admin approves it first.
struct HalalVerificationSection: View {
    let restaurantId: Int
    let restaurantName: String
    let halal: HalalInfo
    /// Called after a vouch is sent, so the host sheet can reload details and show "waiting for review".
    var onVouched: (() -> Void)? = nil

    @State private var showingReport = false
    @State private var showingOwnerClaim = false
    @State private var showingHistory = false

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("HALAL")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
                .tracking(0.5)

            HalalBadge(display: halal.display, size: .regular) { if canVouch { showingReport = true } }

            if let line = halal.display.verificationLabel {
                Text(line)
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
            }
            if let expires = halal.verification?.expiresAt, halal.status == .certified {
                Text("Certificate valid until \(HalalDates.display(expires))")
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
            }

            if let mine = halal.myReport {
                MyVouchStatusRow(report: mine) { showingReport = true }
            } else {
                vouchPrompt
            }

            ForEach(halal.reports) { report in
                HalalReportCard(report: report)
            }

            Text("In testing — halal info may be incomplete. Always double-check at the restaurant.")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)

            HStack(spacing: 16) {
                if halal.historyCount > 1 {
                    // Sheet, not NavigationLink — detail sheets aren't always inside a NavigationStack.
                    Button("History (\(halal.historyCount))") { showingHistory = true }
                }
                Spacer()
                Button("Own this place?") { showingOwnerClaim = true }
                    .foregroundStyle(.secondary)
            }
            .font(.makanBody(13))
            .tint(Color.sambalRed)
        }
        .padding(14)
        .background(Color.kicap.opacity(0.04))
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .sheet(isPresented: $showingReport, onDismiss: { onVouched?() }) {
            HalalReportSheet(restaurantId: restaurantId, restaurantName: restaurantName, existing: halal.myReport)
        }
        .sheet(isPresented: $showingHistory) {
            NavigationStack {
                HalalHistoryView(restaurantId: restaurantId, restaurantName: restaurantName)
            }
        }
        .sheet(isPresented: $showingOwnerClaim) {
            OwnerClaimSheet(restaurantId: restaurantId, restaurantName: restaurantName)
        }
    }
}

extension HalalVerificationSection {
    /// A pending vouch is locked until reviewed — the API would refuse a second one anyway.
    private var canVouch: Bool { halal.myReport?.status != "pending" }

    /// The primary ask when this user hasn't vouched yet — one tap into the vouch sheet.
    private var vouchPrompt: some View {
        Button {
            showingReport = true
        } label: {
            HStack(spacing: 10) {
                Image(systemName: "hand.thumbsup.fill")
                    .font(.system(size: 16))
                VStack(alignment: .leading, spacing: 2) {
                    Text(halal.status == .unknown ? "Know if this place is halal?" : "Is this still accurate?")
                        .font(.makanBody(14))
                    Text("Vouch for it — our team reviews every vouch")
                        .font(.makanBody(11))
                        .foregroundStyle(.secondary)
                }
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.system(size: 12, weight: .semibold))
                    .foregroundStyle(.secondary)
            }
            .foregroundStyle(Color.kicap)
            .padding(12)
            .background(Color.white, in: RoundedRectangle(cornerRadius: 12))
        }
        .buttonStyle(.plain)
    }
}

/// The user's own vouch and where it is in review.
private struct MyVouchStatusRow: View {
    let report: HalalMyReport
    let onEdit: () -> Void

    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: icon)
                .foregroundStyle(tint)
            VStack(alignment: .leading, spacing: 3) {
                Text(title).font(.makanBody(13)).foregroundStyle(Color.kicap)
                if let note = report.reviewNote {
                    Text(note).font(.makanBody(12)).foregroundStyle(.secondary)
                }
                if report.status == "changes_requested" || report.status == "draft" {
                    Button(report.status == "draft" ? "Finish your vouch" : "Add evidence", action: onEdit)
                        .font(.makanBody(12))
                        .tint(Color.sambalRed)
                }
            }
            Spacer(minLength: 0)
        }
        .padding(12)
        .background(tint.opacity(0.08), in: RoundedRectangle(cornerRadius: 12))
    }

    private var claimText: String { report.claim?.pickerLabel.lowercased() ?? "halal status" }

    private var title: String {
        switch report.status {
        case "pending": "You vouched \(claimText) — waiting for review"
        case "changes_requested": "Our team needs a bit more evidence"
        case "approved": "Your vouch was approved — thank you!"
        case "rejected": "Your vouch couldn't be verified"
        default: "Your vouch isn't sent yet"
        }
    }

    private var icon: String {
        switch report.status {
        case "approved": "checkmark.circle.fill"
        case "rejected": "xmark.circle"
        case "changes_requested": "exclamationmark.circle"
        default: "clock"
        }
    }

    private var tint: Color {
        switch report.status {
        case "approved": .pandan
        case "rejected", "changes_requested": .sambalRed
        default: .kunyit
        }
    }
}

private struct HalalReportCard: View {
    let report: HalalPublicReport

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack(spacing: 6) {
                if report.isCurrent {
                    Text("CURRENT EVIDENCE")
                        .font(.makanBody(9))
                        .foregroundStyle(Color.pandan)
                        .padding(.horizontal, 6).padding(.vertical, 2)
                        .background(Color.pandan.opacity(0.12), in: Capsule())
                }
                Text(report.userName).font(.makanBody(12)).foregroundStyle(Color.kicap)
                if let date = report.approvedAt {
                    Text("· \(HalalDates.display(date))").font(.makanBody(11)).foregroundStyle(.secondary)
                }
            }
            // Claim vs what the moderator concluded — both facts are kept, so show both when they differ.
            if let claim = report.claim, let resolved = report.resolvedStatus, claim != resolved {
                Text("Reported \(claim.pickerLabel.lowercased()) · reviewed as \(resolved.pickerLabel.lowercased())")
                    .font(.makanBody(11))
                    .foregroundStyle(.secondary)
            }
            if let comment = report.comment {
                Text("\"\(comment)\"")
                    .font(.makanBody(13))
                    .italic()
                    .foregroundStyle(Color.kicap.opacity(0.85))
            }
            if !report.photos.isEmpty {
                PlaceCommunityPhotoStrip(urls: report.photos.map(\.url))
            }
        }
        .padding(10)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white.opacity(report.isCurrent ? 1 : 0.6), in: RoundedRectangle(cornerRadius: 12))
        .opacity(report.isCurrent ? 1 : 0.8)
    }
}

/// Sendable FormatStyles (not shared DateFormatter instances) — Swift 6 strict concurrency.
enum HalalDates {
    private static let dateOnly = Date.ISO8601FormatStyle(timeZone: .current).year().month().day()

    static func display(_ raw: String) -> String {
        let date = (try? Date(raw, strategy: .iso8601)) ?? (try? Date(raw, strategy: dateOnly))
        return date?.formatted(date: .abbreviated, time: .omitted) ?? raw
    }

    static func apiDate(_ date: Date) -> String { date.formatted(dateOnly) }

    static func parse(_ raw: String) -> Date? { try? Date(raw, strategy: dateOnly) }

    static func parseForTests(_ raw: String) -> Date? { parse(raw) }
}
