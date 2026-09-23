import SwiftUI

// Makan Brain pieces for ResultView — kept separate so ResultView only decides *where* they go.

/// "Kenapa ni?" — up to one reason per family (fits you · beat the others · why now), the
/// deciding factor, and an optional "What mattered most?" entry into the what-if sheet.
struct KenapaNiSection: View {
    let reasons: [PickReason]
    let decidingFactor: String?
    let revealedCount: Int
    let hasWhatIf: Bool
    let onWhatIf: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Kenapa ni?")
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
                .accessibilityAddTraits(.isHeader)

            ForEach(Array(reasons.enumerated()), id: \.element) { index, reason in
                HStack(alignment: .firstTextBaseline, spacing: 8) {
                    Text(reason.icon)
                        .accessibilityHidden(true)
                    Text(reason.text)
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .opacity(index < revealedCount ? 1 : 0)
                .offset(x: index < revealedCount ? 0 : -6)
                .animation(Motion.quick, value: revealedCount)
            }

            if let decidingFactor {
                Text(decidingFactor)
                    .font(.makanBody(12))
                    .foregroundStyle(Color.kicap.opacity(0.6))
                    .padding(.top, 2)
                    .opacity(revealedCount >= reasons.count ? 1 : 0)
            }

            if hasWhatIf {
                Button(action: onWhatIf) {
                    Label("What mattered most?", systemImage: "arrow.triangle.branch")
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .frame(minHeight: 36)
                }
                .buttonStyle(.plain)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(14)
        .background(Color.kicap.opacity(0.04))
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .accessibilityElement(children: .contain)
    }
}

struct FitBadge: View {
    let fit: PickFit

    var body: some View {
        let (icon, color): (String, Color) = switch fit {
        case .strong: ("🔥", Color.sambalRed)
        case .good: ("👌", Color.pandan)
        case .wildcard: ("🎲", Color.kunyit)
        }
        HStack(spacing: 4) {
            Text(icon).accessibilityHidden(true)
            Text(fit.label)
        }
        .font(.makanBody(12))
        .foregroundStyle(color)
        .padding(.horizontal, 10)
        .padding(.vertical, 4)
        .background(color.opacity(0.12))
        .clipShape(Capsule())
        .accessibilityLabel(fit.label)
    }
}

/// The real funnel, not loading copy: "Checked 31 nearby spots → Removed 7 closed → …".
struct ThinkingTraceView: View {
    let lines: [String]
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var visible = 0

    static func duration(for lines: [String]) -> Duration {
        .milliseconds(min(800, lines.count * 120 + 200))
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            ForEach(Array(lines.enumerated()), id: \.offset) { index, line in
                HStack(spacing: 8) {
                    Image(systemName: index == lines.count - 1 ? "checkmark.circle.fill" : "arrow.down")
                        .font(.system(size: 11, weight: .semibold))
                        .foregroundStyle(index == lines.count - 1 ? Color.sambalRed : .secondary)
                        .frame(width: 14)
                    Text(line)
                        .font(.makanBody(13))
                        .foregroundStyle(index == lines.count - 1 ? Color.kicap : .secondary)
                }
                .opacity(index < visible ? 1 : 0)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
        .task {
            if reduceMotion {
                visible = lines.count
                return
            }
            for index in lines.indices {
                withAnimation(Motion.quick) { visible = index + 1 }
                try? await Task.sleep(for: .milliseconds(110))
            }
        }
    }
}

/// "Not quite?" — reranks the stored pool instantly; composable up to twice per decision.
struct TuneRow: View {
    let used: [TuneDirection]
    let onTune: (TuneDirection) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Not quite?")
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(TuneDirection.allCases) { direction in
                        let isUsed = used.contains(direction)
                        Button {
                            UIImpactFeedbackGenerator(style: .light).impactOccurred()
                            onTune(direction)
                        } label: {
                            Label(direction.label, systemImage: direction.systemImage)
                                .font(.makanBody(13))
                                .foregroundStyle(isUsed ? .white : Color.kicap)
                                .padding(.horizontal, 12)
                                .frame(minHeight: 36)
                                .background(isUsed ? Color.sambalRed : Color.kicap.opacity(0.06))
                                .clipShape(Capsule())
                        }
                        .buttonStyle(.plain)
                        .disabled(isUsed)
                        .accessibilityAddTraits(isUsed ? .isSelected : [])
                    }
                }
            }
        }
    }
}

struct SearchWiderBanner: View {
    let message: String
    let adjustment: SearchWiderAdjustment
    let onSearch: () -> Void

    var body: some View {
        VStack(spacing: 10) {
            Text(message)
                .font(.makanBody(14))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
            Button(action: onSearch) {
                Text(String(format: "Search within %.1f km", adjustment.distanceKm))
                    .font(.makanBody(14))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 18)
                    .frame(minHeight: 44)
                    .background(Color.sambalRed)
                    .clipShape(Capsule())
            }
            .buttonStyle(.plain)
        }
        .frame(maxWidth: .infinity)
        .padding(14)
        .background(Color.kunyit.opacity(0.15))
        .clipShape(RoundedRectangle(cornerRadius: 18))
    }
}

/// Shown during a reroll: "Help me learn — kenapa tak nak?". Every tap is fire-and-forget;
/// the reroll never waits on it. "Not feeling it" opens one optional extra layer.
struct WhyNotChips: View {
    let onAnswer: (WhyNotReason, WhyNotDetail?) -> Void
    @State private var answered = false
    @State private var expanded = false

    var body: some View {
        VStack(spacing: 10) {
            Text(answered ? "Noted 👍" : "Help me learn — kenapa tak nak?")
                .font(.makanBody(13))
                .foregroundStyle(.secondary)

            if !answered {
                FlowChips(items: expanded ? WhyNotDetail.allCases.map { ($0.rawValue, $0.label) } : WhyNotReason.allCases.map { ($0.rawValue, $0.label) }) { raw in
                    if expanded {
                        onAnswer(.notFeelingIt, WhyNotDetail(rawValue: raw))
                        answered = true
                    } else if raw == WhyNotReason.notFeelingIt.rawValue {
                        withAnimation(Motion.quick) { expanded = true }
                    } else if let reason = WhyNotReason(rawValue: raw) {
                        onAnswer(reason, nil)
                        answered = true
                    }
                }
                if expanded {
                    Button("Skip") {
                        onAnswer(.notFeelingIt, nil)
                        answered = true
                    }
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
                }
            }
        }
    }
}

private struct FlowChips: View {
    let items: [(String, String)]
    let onTap: (String) -> Void

    var body: some View {
        let rows = stride(from: 0, to: items.count, by: 2).map { Array(items[$0..<min($0 + 2, items.count)]) }
        VStack(spacing: 8) {
            ForEach(rows.indices, id: \.self) { row in
                HStack(spacing: 8) {
                    ForEach(rows[row], id: \.0) { item in
                        Button {
                            UIImpactFeedbackGenerator(style: .light).impactOccurred()
                            onTap(item.0)
                        } label: {
                            Text(item.1)
                                .font(.makanBody(13))
                                .foregroundStyle(Color.kicap)
                                .padding(.horizontal, 12)
                                .frame(minHeight: 36)
                                .background(Color.kicap.opacity(0.06))
                                .clipShape(Capsule())
                        }
                        .buttonStyle(.plain)
                    }
                }
            }
        }
    }
}

/// "What mattered most?" — counterfactual winners replayed from the stored pool.
struct WhatIfSheet: View {
    let entries: [WhatIfEntry]
    let isLoading: Bool
    let onChoose: (WhatIfEntry) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            List {
                Section {
                    Text("Each line removes one thing I weighed, and shows who would've won instead.")
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                        .listRowBackground(Color.clear)
                }
                if isLoading {
                    HStack { Spacer(); ProgressView(); Spacer() }.listRowBackground(Color.clear)
                } else if entries.isEmpty {
                    Text("Nothing would change it — this one wins on every count.")
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap)
                }
                ForEach(entries) { entry in
                    Button {
                        onChoose(entry)
                        dismiss()
                    } label: {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(entry.label)
                                .font(.makanBody(13))
                                .foregroundStyle(.secondary)
                            HStack {
                                Text(entry.winner.name ?? "Another place")
                                    .font(.makanBody(16))
                                    .foregroundStyle(Color.kicap)
                                Spacer()
                                Text("Switch")
                                    .font(.makanBody(13))
                                    .foregroundStyle(Color.sambalRed)
                            }
                        }
                        .padding(.vertical, 4)
                    }
                    .accessibilityHint("Switches your pick to this place")
                }
            }
            .scrollContentBackground(.hidden)
            .background(Color.nasiCream.ignoresSafeArea())
            .navigationTitle("What mattered most?")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) { Button("Done") { dismiss() } }
            }
        }
    }
}
