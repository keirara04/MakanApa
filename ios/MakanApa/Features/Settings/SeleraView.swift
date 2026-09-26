import SwiftUI

/// "Your Selera": what Makan Brain thinks it knows about your taste, with the evidence behind
/// each guess, so you can audit and correct it. Constraints (halal, budget, distance) are shown
/// separately: they're requirements, not taste.
///
/// The API still sends an emoji `icon` per trait/constraint (older builds render it). This screen
/// ignores it and draws SF Symbols chosen from the key, so the page stays emoji-free.
struct SeleraView: View {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var selera: SeleraResponse?
    @State private var isLoading = true
    @State private var expanded: Set<String> = []
    @State private var confirmingReset = false

    private static let stages = ["starting", "learning", "knowing", "strong"]

    /// `initial` seeds the screen for previews; in the app it's nil and `.task` fetches.
    init(initial: SeleraResponse? = nil) {
        _selera = State(initialValue: initial)
        _isLoading = State(initialValue: initial == nil)
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 28) {
                if let selera {
                    stageCard(selera)
                    traitsSection(selera)
                    constraintsSection(selera)
                    resetSection
                } else if isLoading {
                    ProgressView()
                        .frame(maxWidth: .infinity)
                        .padding(.top, 80)
                } else {
                    Text(Copy.connectionErrorDetail)
                        .font(.makanBody(15))
                        .foregroundStyle(.secondary)
                        .frame(maxWidth: .infinity)
                        .padding(.top, 80)
                }
            }
            .padding(.horizontal, 20)
            .padding(.top, 8)
            .padding(.bottom, 40)
        }
        .background(Color.nasiCream.ignoresSafeArea())
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

    // MARK: - Stage

    private func stageCard(_ selera: SeleraResponse) -> some View {
        let index = Self.stages.firstIndex(of: selera.stage) ?? 0

        return VStack(alignment: .leading, spacing: 16) {
            HStack(alignment: .center, spacing: 14) {
                MascotView(mood: mascotMood(selera.stage), size: 64)
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 4) {
                    Text(stageTitle(selera.stage))
                        .font(.makanDisplay(22))
                        .foregroundStyle(Color.kicap)
                    Text(stageDetail(selera.stage))
                        .font(.makanBody(14))
                        .foregroundStyle(Color.kicap.opacity(0.7))
                        .fixedSize(horizontal: false, vertical: true)
                }
            }

            VStack(alignment: .leading, spacing: 8) {
                HStack(spacing: 6) {
                    ForEach(Self.stages.indices, id: \.self) { i in
                        Capsule()
                            .fill(i <= index ? Color.sambalRed : Color.kicap.opacity(0.1))
                            .frame(height: 6)
                    }
                }
                Text(selera.signalCount == 1 ? "1 pick so far" : "\(selera.signalCount) picks so far")
                    .font(.makanBody(12))
                    .foregroundStyle(Color.kicap.opacity(0.6))
            }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Stage \(index + 1) of \(Self.stages.count), \(selera.signalCount) picks so far")
        }
        .padding(18)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 24))
        .overlay { RoundedRectangle(cornerRadius: 24).strokeBorder(Color.kicap.opacity(0.06)) }
    }

    // MARK: - Traits

    private func traitsSection(_ selera: SeleraResponse) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            sectionHeader("What I've picked up", detail: selera.traits.isEmpty ? nil : "Tap one to see why, or correct it.")

            if selera.traits.isEmpty {
                HStack(alignment: .top, spacing: 12) {
                    symbolBadge("sparkles", tint: .kunyit)
                    Text("Still learning. Pick a few places and I'll figure out your selera.")
                        .font(.makanBody(15))
                        .foregroundStyle(Color.kicap.opacity(0.75))
                        .fixedSize(horizontal: false, vertical: true)
                }
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 20))
            } else {
                VStack(spacing: 10) {
                    ForEach(selera.traits) { trait in
                        traitCard(trait)
                    }
                }
            }
        }
    }

    private func traitCard(_ trait: SeleraTrait) -> some View {
        let isExpanded = expanded.contains(trait.key)

        return VStack(alignment: .leading, spacing: 14) {
            Button {
                withAnimation(reduceMotion ? nil : Motion.quick) {
                    if isExpanded { expanded.remove(trait.key) } else { expanded.insert(trait.key) }
                }
            } label: {
                HStack(spacing: 12) {
                    symbolBadge(Self.symbol(forTrait: trait.key), tint: .sambalRed)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(trait.label)
                            .font(.system(.headline, design: .rounded, weight: .bold))
                            .foregroundStyle(Color.kicap)
                        strengthPill(trait.strength)
                    }
                    Spacer(minLength: 0)
                    Image(systemName: "chevron.down")
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(Color.kicap.opacity(0.4))
                        .rotationEffect(.degrees(isExpanded ? 180 : 0))
                }
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("\(trait.label), \(strengthLabel(trait.strength))")
            .accessibilityHint(isExpanded ? "Hides the reason" : "Shows why MakanApa thinks this")

            if isExpanded {
                VStack(alignment: .leading, spacing: 12) {
                    HStack(alignment: .top, spacing: 8) {
                        Image(systemName: "lightbulb")
                            .foregroundStyle(Color.kicap.opacity(0.5))
                            .accessibilityHidden(true)
                        Text(trait.evidence)
                            .font(.makanBody(14))
                            .foregroundStyle(Color.kicap.opacity(0.75))
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    .padding(12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color.nasiCream, in: RoundedRectangle(cornerRadius: 14))

                    ViewThatFits(in: .horizontal) {
                        HStack(spacing: 8) { feedbackButtons(trait) }
                        VStack(alignment: .leading, spacing: 8) { feedbackButtons(trait) }
                    }
                }
                .transition(.opacity.combined(with: .move(edge: .top)))
            }
        }
        .padding(14)
        .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 20))
        .overlay { RoundedRectangle(cornerRadius: 20).strokeBorder(Color.kicap.opacity(0.06)) }
    }

    @ViewBuilder
    private func feedbackButtons(_ trait: SeleraTrait) -> some View {
        feedbackButton("More like this", systemImage: "hand.thumbsup") {
            try await APIClient.seleraTraitFeedback(key: trait.key, kind: "more")
        }
        feedbackButton("Less", systemImage: "minus.circle") {
            try await APIClient.seleraTraitFeedback(key: trait.key, kind: "less")
        }
        feedbackButton("Not really", systemImage: "xmark", tint: .sambalRed) {
            try await APIClient.seleraTraitFeedback(key: trait.key, kind: "not_really")
        }
        feedbackButton("Hide", systemImage: "eye.slash") {
            try await APIClient.muteSeleraTrait(key: trait.key)
        }
    }

    private func feedbackButton(_ title: String, systemImage: String, tint: Color = .kicap,
                                action: @escaping () async throws -> SeleraResponse) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            Task { await update(action) }
        } label: {
            Label(title, systemImage: systemImage)
                .font(.makanBody(13))
                .lineLimit(1)
                .padding(.horizontal, 12)
                .frame(minHeight: 36)
                .foregroundStyle(tint)
                .background(tint.opacity(0.08), in: Capsule())
        }
        .buttonStyle(PressCompressStyle())
    }

    // MARK: - Constraints

    private func constraintsSection(_ selera: SeleraResponse) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            sectionHeader("Always applied", detail: "Requirements, not taste. Our system never learns around these.")

            VStack(spacing: 0) {
                ForEach(Array(selera.constraints.enumerated()), id: \.element.id) { i, constraint in
                    HStack(spacing: 12) {
                        symbolBadge(Self.symbol(forConstraint: constraint.key), tint: .pandan)
                        Text(constraint.label)
                            .font(.makanBody(16))
                            .foregroundStyle(Color.kicap)
                        Spacer(minLength: 8)
                        constraintValue(constraint)
                    }
                    .padding(.horizontal, 14)
                    .padding(.vertical, 12)
                    .accessibilityElement(children: .combine)

                    if i < selera.constraints.count - 1 {
                        Divider().padding(.leading, 62)
                    }
                }
            }
            .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 20))
            .overlay { RoundedRectangle(cornerRadius: 20).strokeBorder(Color.kicap.opacity(0.06)) }
        }
    }

    @ViewBuilder
    private func constraintValue(_ constraint: SeleraConstraint) -> some View {
        if constraint.editIn == "each_decision" {
            Text("Set each time")
                .font(.makanBody(14))
                .foregroundStyle(Color.kicap.opacity(0.55))
        } else {
            let isOn = constraint.value ?? false
            Text(isOn ? "On" : "Off")
                .font(.makanBody(13))
                .foregroundStyle(isOn ? Color.pandan : Color.kicap.opacity(0.6))
                .padding(.horizontal, 10)
                .padding(.vertical, 4)
                .background((isOn ? Color.pandan : Color.kicap).opacity(isOn ? 0.14 : 0.06), in: Capsule())
        }
    }

    // MARK: - Reset

    private var resetSection: some View {
        VStack(spacing: 10) {
            Button(role: .destructive) {
                confirmingReset = true
            } label: {
                Label("Reset my Selera", systemImage: "arrow.counterclockwise")
                    .font(.makanBody(16))
                    .foregroundStyle(Color.sambalRed)
                    .frame(maxWidth: .infinity, minHeight: 50)
                    .overlay { Capsule().strokeBorder(Color.sambalRed.opacity(0.35), lineWidth: 1.5) }
                    .contentShape(Capsule())
            }
            .buttonStyle(PressCompressStyle())

            Text("Starts learning from scratch. Your past picks stay in your history.")
                .font(.makanBody(12))
                .foregroundStyle(Color.kicap.opacity(0.55))
                .multilineTextAlignment(.center)
                .frame(maxWidth: .infinity)
        }
        .padding(.top, 4)
    }

    // MARK: - Pieces

    private func sectionHeader(_ title: String, detail: String?) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title)
                .font(.makanDisplay(18))
                .foregroundStyle(Color.kicap)
                .accessibilityAddTraits(.isHeader)
            if let detail {
                Text(detail)
                    .font(.makanBody(13))
                    .foregroundStyle(Color.kicap.opacity(0.6))
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
        .padding(.horizontal, 4)
    }

    private func symbolBadge(_ name: String, tint: Color) -> some View {
        Image(systemName: name)
            .font(.system(size: 17, weight: .semibold))
            .foregroundStyle(tint)
            .frame(width: 38, height: 38)
            .background(tint.opacity(0.12), in: Circle())
            .accessibilityHidden(true)
    }

    private func strengthPill(_ strength: String) -> some View {
        let color: Color = switch strength {
        case "strong": .sambalRed
        case "medium": .kicap
        default: .kicap.opacity(0.6)
        }
        return Text(strengthLabel(strength))
            .font(.makanBody(11))
            .foregroundStyle(color)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(strength == "medium" ? Color.kunyit.opacity(0.3) : color.opacity(0.1), in: Capsule())
    }

    private func strengthLabel(_ strength: String) -> String {
        switch strength {
        case "strong": "Strong signal"
        case "medium": "Getting clearer"
        default: "Early hint"
        }
    }

    // MARK: - Data

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
            withAnimation(reduceMotion ? nil : Motion.standard) { selera = updated }
        }
    }

    // MARK: - Copy

    private func mascotMood(_ stage: String) -> MascotMood {
        switch stage {
        case "strong": .celebrate
        case "knowing": .idle
        default: .thinking
        }
    }

    private func stageTitle(_ stage: String) -> String {
        switch stage {
        case "learning": "Learning your selera"
        case "knowing": "Getting to know you"
        case "strong": "I know your selera"
        default: "Just getting started"
        }
    }

    private func stageDetail(_ stage: String) -> String {
        switch stage {
        case "starting": "Pick and accept a few places. Every choice teaches me a bit more."
        case "learning": "A few patterns so far. Correct anything that's off."
        case "knowing": "Your picks are shaping recommendations now."
        default: "Built from a lot of your decisions. Still editable anytime."
        }
    }

    // MARK: - Symbols (keys come from api/app/Services/Brain/SeleraTraits.php)

    static func symbol(forTrait key: String) -> String {
        let parts = key.split(separator: ":").map(String.init)
        guard let dim = parts.first else { return "fork.knife" }

        switch dim {
        case "price":
            let band = Int(parts.last ?? "") ?? 2
            return band <= 1 ? "banknote" : band == 2 ? "wallet.bifold" : "sparkles"
        case "slot":
            switch parts.count > 1 ? parts[1] : "" {
            case "breakfast": return "sunrise.fill"
            case "lunch": return "sun.max.fill"
            case "teatime": return "cup.and.saucer.fill"
            case "dinner": return "sunset.fill"
            case "supper": return "moon.stars.fill"
            case "weekend": return "calendar"
            default: return "clock"
            }
        case "vibe":
            return "chair.lounge.fill"
        default:
            switch parts.last ?? "" {
            case "cafe", "drinks": return "cup.and.saucer.fill"
            case "dessert", "bakery": return "birthday.cake.fill"
            case "seafood", "sushi": return "fish.fill"
            case "bbq", "steak": return "flame.fill"
            case "burger", "fast_food", "pizza": return "takeoutbag.and.cup.and.straw.fill"
            case "breakfast": return "sunrise.fill"
            default: return "fork.knife"
            }
        }
    }

    static func symbol(forConstraint key: String) -> String {
        switch key {
        case "halal": "checkmark.seal.fill"
        case "budget": "banknote"
        case "distance": "location.fill"
        default: "slider.horizontal.3"
        }
    }
}

#Preview("Learning, with traits") {
    NavigationStack {
        SeleraView(initial: SeleraResponse(
            stage: "learning",
            signalCount: 7,
            traits: [
                SeleraTrait(key: "category:nasi", icon: "", label: "Nasi person", strength: "strong",
                            evidence: "5 of your last 7 accepted picks were nasi"),
                SeleraTrait(key: "price:1", icon: "", label: "Budget-conscious", strength: "medium",
                            evidence: "Based on the price range you usually accept"),
                SeleraTrait(key: "slot:supper:mamak", icon: "", label: "Supper = mamak", strength: "emerging",
                            evidence: "You picked mamak 4 times at supper"),
            ],
            constraints: [
                SeleraConstraint(key: "halal", label: "Hide non-halal", icon: "", value: true, editIn: "settings"),
                SeleraConstraint(key: "budget", label: "Budget", icon: "", value: nil, editIn: "each_decision"),
                SeleraConstraint(key: "distance", label: "Distance", icon: "", value: nil, editIn: "each_decision"),
            ]
        ))
    }
}

#Preview("Just getting started") {
    NavigationStack {
        SeleraView(initial: SeleraResponse(
            stage: "starting",
            signalCount: 0,
            traits: [],
            constraints: [
                SeleraConstraint(key: "halal", label: "Hide non-halal", icon: "", value: false, editIn: "settings"),
                SeleraConstraint(key: "budget", label: "Budget", icon: "", value: nil, editIn: "each_decision"),
                SeleraConstraint(key: "distance", label: "Distance", icon: "", value: nil, editIn: "each_decision"),
            ]
        ))
    }
}
