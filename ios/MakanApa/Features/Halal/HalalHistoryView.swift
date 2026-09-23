import SwiftUI

/// Public verification ledger — "why does MakanApa say this, and what did it say before".
struct HalalHistoryView: View {
    let restaurantId: Int
    let restaurantName: String

    @State private var entries: [HalalHistoryEntry] = []
    @State private var nextPage: Int? = 1
    @State private var isLoading = false

    var body: some View {
        List {
            ForEach(entries) { entry in
                VStack(alignment: .leading, spacing: 4) {
                    HStack {
                        Text(entry.status.pickerLabel).font(.makanBody(14)).foregroundStyle(Color.kicap)
                        if entry.state == "active" {
                            Text("Current").font(.makanBody(10)).foregroundStyle(Color.pandan)
                        } else {
                            Text(entry.state.capitalized).font(.makanBody(10)).foregroundStyle(.secondary)
                        }
                    }
                    Text(methodLabel(entry)).font(.makanBody(12)).foregroundStyle(.secondary)
                    if let summary = entry.summary {
                        Text(summary).font(.makanBody(12)).foregroundStyle(Color.kicap.opacity(0.8))
                    }
                    if let from = entry.effectiveFrom {
                        Text(HalalDates.display(from)).font(.makanBody(11)).foregroundStyle(.secondary)
                    }
                }
                .padding(.vertical, 4)
            }
            if nextPage != nil {
                ProgressView()
                    .frame(maxWidth: .infinity)
                    .task { await loadMore() }
            }
        }
        .navigationTitle("Halal history")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func methodLabel(_ entry: HalalHistoryEntry) -> String {
        let method = switch entry.method {
        case "automatic": "Flagged automatically from its listing"
        case "registry_verified": "Checked against the official registry"
        case "administrator_override": "Set by the MakanApa team"
        default: entry.evidenceSource == "restaurant_owner" ? "Owner evidence, reviewed" : "Community evidence, reviewed"
        }
        return [method, entry.authority?.label].compactMap { $0 }.joined(separator: " · ")
    }

    @MainActor
    private func loadMore() async {
        guard let page = nextPage, !isLoading else { return }
        isLoading = true
        defer { isLoading = false }
        guard let response = try? await APIClient.halalHistory(restaurantId: restaurantId, page: page) else {
            nextPage = nil
            return
        }
        entries.append(contentsOf: response.entries)
        nextPage = response.nextPage
    }
}
