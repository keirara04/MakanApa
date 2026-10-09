import SwiftUI

// "Ambassador picks": places a community's ambassador hand-picks, shown to its members as a
// horizontal rail in Community, plus the "Add to my picks" control ambassadors see on a place.

/// The signed-in ambassador's own picks (place → note). Plain observable store, never
/// `@AppStorage` — the Nearby map's ancestors must not observe UserDefaults (render loop).
@MainActor
@Observable
final class AmbassadorPickStore {
    static let shared = AmbassadorPickStore()

    private(set) var notes: [Int: String] = [:]
    private(set) var picked: Set<Int> = []
    private(set) var max = 8
    /// Keyed by account so a different sign-in never inherits the previous ambassador's picks.
    private var loadedForUserId: Int?

    func loadIfNeeded() async {
        guard case .authenticated(let user) = AuthStore.shared.session, user.ambassadorOf != nil,
              loadedForUserId != user.id else { return }
        picked = []
        notes = [:]
        guard let response = try? await APIClient.myAmbassadorPicks() else { return }
        picked = Set(response.picks.map(\.restaurantId))
        notes = Dictionary(uniqueKeysWithValues: response.picks.compactMap { pick in pick.note.map { (pick.restaurantId, $0) } })
        max = response.max
        loadedForUserId = user.id
    }

    func save(restaurantId: Int, note: String?) async throws {
        let response = try await APIClient.saveAmbassadorPick(restaurantId: restaurantId, note: note)
        picked.insert(restaurantId)
        notes[restaurantId] = response.pick.note
    }

    func remove(restaurantId: Int) async throws {
        _ = try await APIClient.removeAmbassadorPick(restaurantId: restaurantId)
        picked.remove(restaurantId)
        notes[restaurantId] = nil
    }

    static var currentRole: AmbassadorRole? {
        if case .authenticated(let user) = AuthStore.shared.session { return user.ambassadorOf }
        return nil
    }
}

// MARK: - Rail

struct AmbassadorPicksSection: View {
    let picks: [AmbassadorPickItem]
    let community: String
    /// The viewer is this community's ambassador — shows the first-pick prompt and Remove.
    let isMine: Bool
    let onSelect: (AmbassadorPickItem) -> Void

    @State private var store = AmbassadorPickStore.shared
    @State private var selections = 0
    /// Removed from here this visit — hidden right away instead of waiting for the next feed load.
    @State private var removed: Set<Int> = []

    private var visiblePicks: [AmbassadorPickItem] { picks.filter { !removed.contains($0.id) } }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Label {
                    Text(Copy.ambassadorPicksTitle)
                        .foregroundStyle(Color.kicap)
                } icon: {
                    Image(systemName: "star.fill")
                        .foregroundStyle(Color.kunyit)
                }
                .font(.headline)
                .accessibilityAddTraits(.isHeader)

                if !visiblePicks.isEmpty {
                    Text(Copy.ambassadorPicksSubtitle(names: pickerNames, community: community))
                        .font(.subheadline)
                        .foregroundStyle(Color.kicapSecondary)
                }
            }

            ScrollView(.horizontal) {
                LazyHStack(alignment: .top, spacing: 12) {
                    if visiblePicks.isEmpty {
                        AmbassadorFirstPickCard()
                    }
                    ForEach(visiblePicks) { pick in
                        Button {
                            selections += 1
                            onSelect(pick)
                        } label: {
                            AmbassadorPickCard(pick: pick)
                        }
                        .buttonStyle(CommunityPressStyle())
                        .contextMenu {
                            if isMine, store.picked.contains(pick.id) {
                                Button(Copy.ambassadorPickRemove, systemImage: "star.slash", role: .destructive) {
                                    Task {
                                        guard (try? await store.remove(restaurantId: pick.id)) != nil else { return }
                                        withAnimation(Motion.standard) { _ = removed.insert(pick.id) }
                                    }
                                }
                            }
                        }
                    }
                }
                .scrollTargetLayout()
            }
            .scrollTargetBehavior(.viewAligned)
            .scrollIndicators(.hidden)
            // Full-bleed rail inside the page's 20pt gutter, so the next card peeks off-screen.
            .contentMargins(.horizontal, 20, for: .scrollContent)
            .padding(.horizontal, -20)
        }
        .sensoryFeedback(.selection, trigger: selections)
        .task { if isMine { await store.loadIfNeeded() } }
    }

    private var pickerNames: [String] {
        var seen = Set<String>()
        return visiblePicks.map(\.ambassador.name).filter { seen.insert($0).inserted }
    }
}

struct AmbassadorPickCard: View {
    let pick: AmbassadorPickItem

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            image
                .frame(height: 140)
                .frame(maxWidth: .infinity)
                .clipped()

            VStack(alignment: .leading, spacing: 6) {
                Text(pick.name)
                    .font(.headline)
                    .foregroundStyle(Color.kicap)
                    .lineLimit(1)

                if !details.isEmpty {
                    Text(details)
                        .font(.footnote)
                        .monospacedDigit()
                        .foregroundStyle(Color.kicapSecondary)
                        .lineLimit(1)
                }

                if let note = pick.note {
                    Text("\u{201C}\(note)\u{201D}")
                        .font(.subheadline)
                        .foregroundStyle(Color.kicap)
                        .lineLimit(2, reservesSpace: true)
                        .multilineTextAlignment(.leading)
                }

                HStack(spacing: 6) {
                    Image(AvatarCharacter(key: pick.ambassador.avatarKey).imageName)
                        .resizable()
                        .scaledToFill()
                        .frame(width: 20, height: 20)
                        .background(Color.nasiCream)
                        .clipShape(Circle())
                        .accessibilityHidden(true)
                    Text(pick.ambassador.name)
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(Color.kicapSecondary)
                        .lineLimit(1)
                }
                .padding(.top, 2)
            }
            .padding(14)
        }
        .frame(width: 240)
        .background(Color.surface, in: .card)
        .clipShape(.card)
        .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
        .contentShape(.card)
        .accessibilityElement(children: .combine)
    }

    /// A community photo when one exists; otherwise the category on a cream tile.
    @ViewBuilder
    private var image: some View {
        let placeholder = Image(systemName: categorySymbol)
            .font(.largeTitle)
            .foregroundStyle(Color.kunyit)
            .frame(maxWidth: .infinity, maxHeight: .infinity)
            .background(Color.nasiCream)

        if let url = pick.photoUrl.flatMap(URL.init(string:)) {
            // RemoteImage (cached, downsampled) — a rail re-shows cards as they scroll back in.
            Color.nasiCream.overlay {
                RemoteImage(url: url, maxPixelSize: 720) { placeholder }
                    .scaledToFill()
            }
        } else {
            placeholder
        }
    }

    /// "Malay · ≈ RM10/person · 1.2 km"
    private var details: String {
        let kind = pick.cuisines.first?.capitalized ?? pick.foodCategory?.replacingOccurrences(of: "_", with: " ").capitalized
        let distance = pick.distanceKm.map { "\($0.formatted(.number.precision(.fractionLength(1)))) km" }
        return [kind, PricePresentation.approximateSpendLabel(for: pick.priceLevel), distance]
            .compactMap { $0 }
            .joined(separator: " · ")
    }

    private var categorySymbol: String {
        switch pick.foodCategory?.replacingOccurrences(of: "_restaurant", with: "") {
        case "cafe", "coffee_shop", "breakfast", "drinks": "cup.and.saucer.fill"
        case "dessert", "bakery": "birthday.cake.fill"
        case "burger", "fast_food", "chicken", "pizza", "sandwich", "western": "takeoutbag.and.cup.and.straw.fill"
        case "sushi", "seafood", "japanese": "fish.fill"
        default: "fork.knife"
        }
    }
}

/// What an ambassador sees in their own community before they've picked anything.
private struct AmbassadorFirstPickCard: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Image(systemName: "star")
                .font(.title2.weight(.semibold))
                .foregroundStyle(Color.kunyit)
            Text(Copy.ambassadorFirstPickTitle)
                .font(.headline)
                .foregroundStyle(Color.kicap)
            Text(Copy.ambassadorFirstPickDetail)
                .font(.subheadline)
                .foregroundStyle(Color.kicapSecondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(16)
        .frame(width: 240, alignment: .leading)
        .frame(minHeight: 180, alignment: .topLeading)
        .overlay(
            RoundedRectangle.card.strokeBorder(Color.hairline, style: StrokeStyle(lineWidth: 1.5, dash: [6, 5]))
        )
    }
}

// MARK: - Add / edit control

/// "☆ Add to my picks" / "★ In your picks" on a place. Renders nothing for non-ambassadors.
struct AmbassadorPickButton: View {
    let restaurantId: Int
    let restaurantName: String

    @State private var store = AmbassadorPickStore.shared
    @State private var showingSheet = false

    var body: some View {
        if AmbassadorPickStore.currentRole != nil {
            let isPicked = store.picked.contains(restaurantId)
            Button {
                showingSheet = true
            } label: {
                Label(isPicked ? Copy.ambassadorInPicks : Copy.ambassadorAddPick, systemImage: isPicked ? "star.fill" : "star")
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(Color.kicap)
                    .symbolEffect(.bounce, value: isPicked)
                    .frame(maxWidth: .infinity, minHeight: 44)
                    .background(isPicked ? Color.kunyit.opacity(0.25) : Color.surface, in: Capsule())
                    .overlay(Capsule().strokeBorder(Color.hairline, lineWidth: 1))
            }
            .buttonStyle(CommunityPressStyle())
            .sensoryFeedback(.success, trigger: isPicked) { _, now in now }
            .task { await store.loadIfNeeded() }
            .sheet(isPresented: $showingSheet) {
                AmbassadorPickSheet(restaurantId: restaurantId, restaurantName: restaurantName)
                    .presentationDetents([.medium])
            }
        }
    }
}

private struct AmbassadorPickSheet: View {
    let restaurantId: Int
    let restaurantName: String

    @Environment(\.dismiss) private var dismiss
    @State private var store = AmbassadorPickStore.shared
    @State private var note = ""
    @State private var isSaving = false
    @State private var errorMessage: String?

    private let noteLimit = 140

    var body: some View {
        NavigationStack {
            VStack(alignment: .leading, spacing: 16) {
                Text(restaurantName)
                    .font(.title3.weight(.bold))
                    .foregroundStyle(Color.kicap)

                TextField(Copy.ambassadorPickNotePlaceholder, text: $note, axis: .vertical)
                    .lineLimit(3...5)
                    .padding(12)
                    .background(Color.surface, in: RoundedRectangle(cornerRadius: Radius.row, style: .continuous))
                    .overlay(RoundedRectangle(cornerRadius: Radius.row, style: .continuous).strokeBorder(Color.hairline, lineWidth: 1))
                    .onChange(of: note) { _, value in
                        if value.count > noteLimit { note = String(value.prefix(noteLimit)) }
                    }

                HStack {
                    if let errorMessage {
                        Text(errorMessage)
                            .font(.footnote)
                            .foregroundStyle(Color.sambalRed)
                    }
                    Spacer()
                    Text("\(note.count)/\(noteLimit)")
                        .font(.footnote)
                        .monospacedDigit()
                        .foregroundStyle(Color.kicapSecondary)
                }

                if store.picked.contains(restaurantId) {
                    Button(Copy.ambassadorPickRemove, role: .destructive) {
                        Task { await run { try await store.remove(restaurantId: restaurantId) } }
                    }
                    .font(.subheadline.weight(.semibold))
                    .frame(minHeight: 44)
                }

                Spacer(minLength: 0)
            }
            .padding(20)
            .background(Color.nasiCream.ignoresSafeArea())
            .navigationTitle(Copy.ambassadorPickSheetTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(Copy.ambassadorPickCancel) { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(Copy.ambassadorPickSave) {
                        let trimmed = note.trimmingCharacters(in: .whitespacesAndNewlines)
                        Task { await run { try await store.save(restaurantId: restaurantId, note: trimmed.isEmpty ? nil : trimmed) } }
                    }
                    .fontWeight(.semibold)
                    .disabled(isSaving)
                }
            }
        }
        .onAppear { note = store.notes[restaurantId] ?? "" }
        .interactiveDismissDisabled(isSaving)
    }

    private func run(_ action: () async throws -> Void) async {
        isSaving = true
        errorMessage = nil
        defer { isSaving = false }
        do {
            try await action()
            dismiss()
        } catch {
            errorMessage = (error as? APIError)?.serverMessage ?? Copy.ambassadorPickFailed
        }
    }
}
