import SwiftUI

/// "Your Selera" — what Makan Brain thinks it knows about your taste, with the evidence behind
/// each guess, so you can audit and correct it. Constraints (halal, budget, distance) are shown
/// separately: they're requirements, not taste.
struct SeleraView: View {
    @State private var selera: SeleraResponse?
    @State private var isLoading = true
    @State private var expanded: Set<String> = []
    @State private var confirmingReset = false

    var body: some View {
        List {
            if let selera {
                Section {
                    VStack(alignment: .leading, spacing: 6) {
                        Text(stageTitle(selera.stage))
                            .font(.makanDisplay(20))
                            .foregroundStyle(Color.kicap)
                        Text(stageDetail(selera.stage))
                            .font(.makanBody(13))
                            .foregroundStyle(.secondary)
                    }
                    .padding(.vertical, 4)
                    .listRowBackground(Color.clear)
                }

                Section("Your Selera") {
                    if selera.traits.isEmpty {
                        Text("Still learning — pick a few places and I'll figure out your selera 🍛")
                            .font(.makanBody(14))
                            .foregroundStyle(.secondary)
                    }
                    ForEach(selera.traits) { trait in
                        traitRow(trait)
                            .swipeActions {
                                Button(role: .destructive) {
                                    Task { await update { try await APIClient.muteSeleraTrait(key: trait.key) } }
                                } label: {
                                    Label("Remove", systemImage: "eye.slash")
                                }
                            }
                    }
                }

                Section {
                    ForEach(selera.constraints) { constraint in
                        HStack {
                            Text(constraint.icon).accessibilityHidden(true)
                            Text(constraint.label).foregroundStyle(Color.kicap)
                            Spacer()
                            Text(constraintValue(constraint))
                                .foregroundStyle(.secondary)
                        }
                        .font(.makanBody(14))
                    }
                } header: {
                    Text("Constraints")
                } footer: {
                    Text("Requirements, not taste — Makan Brain never learns around these.")
                }

                Section {
                    Button("Reset my Selera", role: .destructive) { confirmingReset = true }
                } footer: {
                    Text("Starts learning from scratch. Your past picks stay in your history.")
                }
            } else if isLoading {
                HStack { Spacer(); ProgressView(); Spacer() }
                    .listRowBackground(Color.clear)
            } else {
                Text(Copy.connectionErrorDetail)
                    .foregroundStyle(.secondary)
                    .listRowBackground(Color.clear)
            }
        }
        .navigationTitle("Your Selera")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
        .confirmationDialog("Reset your Selera?", isPresented: $confirmingReset, titleVisibility: .visible) {
            Button("Reset", role: .destructive) {
                Task { await update { try await APIClient.resetSelera() } }
            }
        } message: {
            Text("MakanApa will forget what it learned about your taste.")
        }
    }

    private func traitRow(_ trait: SeleraTrait) -> some View {
        let isExpanded = expanded.contains(trait.key)

        return VStack(alignment: .leading, spacing: 8) {
            Button {
                withAnimation(Motion.quick) {
                    if isExpanded { expanded.remove(trait.key) } else { expanded.insert(trait.key) }
                }
            } label: {
                HStack(spacing: 10) {
                    Text(trait.icon).font(.system(size: 22)).accessibilityHidden(true)
                    Text(trait.label)
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap)
                    Spacer()
                    Text(trait.strength.capitalized)
                        .font(.makanBody(11))
                        .foregroundStyle(strengthColor(trait.strength))
                        .padding(.horizontal, 8)
                        .padding(.vertical, 3)
                        .background(strengthColor(trait.strength).opacity(0.12))
                        .clipShape(Capsule())
                    Image(systemName: "chevron.down")
                        .font(.system(size: 11, weight: .semibold))
                        .foregroundStyle(.secondary)
                        .rotationEffect(.degrees(isExpanded ? 180 : 0))
                }
            }
            .buttonStyle(.plain)
            .accessibilityHint("Shows why MakanApa thinks this")

            if isExpanded {
                VStack(alignment: .leading, spacing: 8) {
                    Text("Why I think this: \(trait.evidence)")
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                    HStack(spacing: 8) {
                        feedbackButton("Not really", kind: "not_really", key: trait.key)
                        feedbackButton("More like this", kind: "more", key: trait.key)
                        feedbackButton("Less", kind: "less", key: trait.key)
                    }
                }
                .transition(.opacity)
            }
        }
        .padding(.vertical, 4)
    }

    private func feedbackButton(_ title: String, kind: String, key: String) -> some View {
        Button(title) {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            Task { await update { try await APIClient.seleraTraitFeedback(key: key, kind: kind) } }
        }
        .font(.makanBody(12))
        .buttonStyle(.bordered)
        .tint(kind == "not_really" ? .sambalRed : .kicap)
    }

    private func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            selera = try await APIClient.selera()
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {}
    }

    private func update(_ call: () async throws -> SeleraResponse) async {
        if let updated = try? await call() {
            withAnimation { selera = updated }
        }
    }

    private func stageTitle(_ stage: String) -> String {
        switch stage {
        case "learning": "Learning your selera"
        case "knowing": "Getting to know you"
        case "strong": "I know your selera 😌"
        default: "Just getting started"
        }
    }

    private func stageDetail(_ stage: String) -> String {
        switch stage {
        case "starting": "Pick and accept a few places — every choice teaches me a bit more."
        case "learning": "A few patterns so far. Correct anything that's off."
        case "knowing": "Your picks are shaping recommendations now."
        default: "Built from a lot of your decisions. Still editable anytime."
        }
    }

    private func strengthColor(_ strength: String) -> Color {
        switch strength {
        case "strong": .sambalRed
        case "medium": .kunyit
        default: .secondary
        }
    }

    private func constraintValue(_ constraint: SeleraConstraint) -> String {
        if constraint.editIn == "each_decision" { return "Set each time" }
        return (constraint.value ?? false) ? "On" : "Off"
    }
}
