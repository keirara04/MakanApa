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
    /// Bumped by every local save/remove, so a load that started before one can't overwrite it.
    private var edits = 0

    func loadIfNeeded() async {
        guard case .authenticated(let user) = AuthStore.shared.session, user.ambassadorOf != nil,
              loadedForUserId != user.id else { return }
        if loadedForUserId != nil { reset() }
        let editsAtStart = edits
        guard let response = try? await APIClient.myAmbassadorPicks(), edits == editsAtStart else { return }
        picked = Set(response.picks.map(\.restaurantId))
        notes = Dictionary(uniqueKeysWithValues: response.picks.compactMap { pick in pick.note.map { (pick.restaurantId, $0) } })
        max = response.max
        loadedForUserId = user.id
    }

    func save(restaurantId: Int, note: String?) async throws {
        let response = try await APIClient.saveAmbassadorPick(restaurantId: restaurantId, note: note)
        edits += 1
        picked.insert(restaurantId)
        notes[restaurantId] = response.pick.note
    }

    func remove(restaurantId: Int) async throws {
        _ = try await APIClient.removeAmbassadorPick(restaurantId: restaurantId)
        edits += 1
        picked.remove(restaurantId)
        notes[restaurantId] = nil
    }

    /// Sign-out: nothing of this account's picks stays on screen for the next one.
    func reset() {
        picked = []
        notes = [:]
        loadedForUserId = nil
        edits += 1
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
    /// "university" | "area" — the crest artwork says UNI, so areas get the gold-star mark.
    let communityType: String?
    /// The viewer is this community's ambassador — shows the first-pick prompt, Remove and Share.
    let isMine: Bool
    let onSelect: (AmbassadorPickItem) -> Void

    @State private var store = AmbassadorPickStore.shared
    @State private var selections = 0
    @State private var showingShareCard = false
    /// Removed from here this visit — hidden right away instead of waiting for the next feed load.
    @State private var removed: Set<Int> = []

    private var visiblePicks: [AmbassadorPickItem] { picks.filter { !removed.contains($0.id) } }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            // The crest as a seal of approval on the row — 64pt is the smallest size it reads at.
            HStack(alignment: .center, spacing: 12) {
                AmbassadorCrestMark(isUniversity: communityType == "university")
                    .frame(width: 64, height: 64)

                VStack(alignment: .leading, spacing: 2) {
                    Text(Copy.ambassadorPicksTitle)
                        .font(.headline)
                        .foregroundStyle(Color.kicap)
                        .accessibilityAddTraits(.isHeader)

                    if !visiblePicks.isEmpty {
                        Text(Copy.ambassadorPicksSubtitle(names: pickerNames, community: community))
                            .font(.subheadline)
                            .foregroundStyle(Color.kicapSecondary)
                    }
                }

                Spacer(minLength: 0)

                if isMine {
                    Button {
                        showingShareCard = true
                    } label: {
                        Image(systemName: "square.and.arrow.up")
                            .font(.headline)
                            .foregroundStyle(Color.kicap)
                            .frame(width: 44, height: 44)
                            .background(Color.surface, in: Circle())
                            .overlay(Circle().strokeBorder(Color.hairline, lineWidth: 1))
                    }
                    .buttonStyle(CommunityPressStyle())
                    .accessibilityLabel(Copy.ambassadorShareCardLabel)
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
        .sheet(isPresented: $showingShareCard) {
            if let role = AmbassadorPickStore.currentRole {
                AmbassadorShareSheet(role: role)
                    .presentationDetents([.large])
            }
        }
    }

    private var pickerNames: [String] {
        var seen = Set<String>()
        return visiblePicks.map(\.ambassador.name).filter { seen.insert($0).inserted }
    }
}

struct AmbassadorPickCard: View {
    let pick: AmbassadorPickItem

    /// Google photo fetched lazily when the card is on screen, only when the ambassador hasn't
    /// added their own (community) photo.
    @State private var googlePhoto: RecommendationResponse.Photo?

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
        .task(id: pick.id) {
            guard pick.photoUrl == nil else { return }
            googlePhoto = await PickPhotoLoader.googlePhoto(for: pick.id)
        }
    }

    /// The ambassador's own photo first, then Google's, else the category on a cream tile.
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
        } else if let googlePhoto, let url = URL(string: googlePhoto.url) {
            Color.nasiCream.overlay {
                RemoteImage(url: url, maxPixelSize: 720) { placeholder }
                    .scaledToFill()
            }
            // Google's terms: a Places photo is shown with its author (or at least its source).
            // Plain text, not GooglePhotoCredit's link — the whole card is already one tap target.
            .overlay(alignment: .bottomLeading) {
                Text(photoCredit(googlePhoto))
                    .font(.caption2.weight(.medium))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 6)
                    .padding(.vertical, 2)
                    .background(.black.opacity(0.45), in: Capsule())
                    .padding(8)
            }
        } else {
            placeholder
        }
    }

    private func photoCredit(_ photo: RecommendationResponse.Photo) -> String {
        let names = photo.authorAttributions.compactMap(\.name).filter { !$0.isEmpty }
        return names.isEmpty ? Copy.googlePhotoCredit : "Photo: \(names.joined(separator: ", "))"
    }

    /// "Malay · ≈ RM10/person · 1.2 km"
    private var details: String {
        let kind = pick.cuisines.first?.capitalized ?? pick.foodCategory?.replacingOccurrences(of: "_", with: " ").capitalized
        // A university/area board isn't anchored on the viewer — a far-off reading (travelling, a
        // simulator's default location) is noise, not information.
        let distance = pick.distanceKm.flatMap { $0 < 50 ? "\($0.formatted(.number.precision(.fractionLength(1)))) km" : nil }
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

/// Google photo for a pick, via the same place-details call the place sheet makes (cached 10 min
/// server-side and shared by everyone). Memoised per session; the signed photo URL lives 30 min,
/// so entries older than 25 min are refetched.
@MainActor
private enum PickPhotoLoader {
    private static var cache: [Int: (photo: RecommendationResponse.Photo?, at: Date)] = [:]

    static func googlePhoto(for restaurantId: Int) async -> RecommendationResponse.Photo? {
        if let hit = cache[restaurantId], Date.now.timeIntervalSince(hit.at) < 25 * 60 { return hit.photo }
        let photo = (try? await APIClient.placeDetails(restaurantId: restaurantId))?.photos.first
        cache[restaurantId] = (photo, .now)
        return photo
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

                // Their own shot of the place — shown on the card once an admin approves it.
                QuickAddPhotoRow(restaurantId: restaurantId, prompt: Copy.ambassadorPickAddPhoto)

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

// MARK: - Crest + share card

/// The crest artwork for universities; a gold star on cream for areas (the art says "UNI").
struct AmbassadorCrestMark: View {
    let isUniversity: Bool

    var body: some View {
        if isUniversity {
            Image("AmbassadorCrest")
                .resizable()
                .scaledToFit()
                .accessibilityHidden(true)
        } else {
            Image(systemName: "star.fill")
                .font(.title)
                .foregroundStyle(Color.kunyit)
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .background(Color.surface, in: Circle())
                .overlay(Circle().strokeBorder(Color.hairline, lineWidth: 1))
                .accessibilityHidden(true)
        }
    }
}
