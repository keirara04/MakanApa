import CoreLocation
import MapKit
import PhotosUI
import SwiftUI

private enum AddPlaceStep: Int, CaseIterable {
    case search, details, location, review, success

    var title: String {
        switch self {
        case .search: "Find"
        case .details: "Details"
        case .location: "Location"
        case .review: "Review"
        case .success: ""
        }
    }
}

/// Add a place / suggest an edit. Every step shares the same frame so the flow reads as one
/// thing: progress header → step heading → content → one pinned primary action (plus an
/// optional secondary), with Back on the left once there's somewhere to go back to.
struct AddPlaceFlow: View {
    @Environment(LocationService.self) private var locationService
    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    /// Skips search and jumps straight into edit mode for a known restaurant — the "Know
    /// the menu? Add it" nudge already knows which place it's for, no reason to make the
    /// user search for it again.
    var prefillExisting: ExistingPlaceResult?
    var prefillShowMenuSection = false

    @State private var step: AddPlaceStep = .search
    @State private var goingForward = true
    @State private var confirmingDiscard = false

    // Search
    @State private var searchQuery = ""
    @State private var searchResults: PlaceSearchResponse?
    @State private var isSearching = false
    @State private var searchError: String?
    @FocusState private var searchFocused: Bool

    // Selection / submission shape
    @State private var submissionType: SubmissionType = .newPlace
    @State private var sourceType: SubmissionSourceType = .manual
    @State private var googlePlaceId: String?
    @State private var restaurantId: Int?
    @State private var locationSource: SubmissionLocationSource = .currentLocation

    // Essential details
    @State private var name = ""
    @State private var foodCategory = ""
    @State private var averageSpend = ""
    @State private var address = ""

    // Optional details — leaving these blank is fine and expected
    @State private var phone = ""
    @State private var instagramHandle = ""
    @State private var tiktokHandle = ""
    @State private var websiteUrl = ""
    @State private var menuItems: [MenuItem] = []
    @State private var notes = ""

    // Details step progressive disclosure
    @State private var showingMoreDetails = false
    @State private var showingMenuSection = false
    @State private var showSpendError = false
    @State private var triedContinue = false

    // Original values, snapshotted at selection time, diffed at submit time to build changedFields
    @State private var originalName = ""
    @State private var originalFoodCategory = ""
    @State private var originalAverageSpend = ""
    @State private var originalAddress = ""
    @State private var originalPhone = ""
    @State private var originalInstagramHandle = ""
    @State private var originalTiktokHandle = ""
    @State private var originalWebsiteUrl = ""
    @State private var originalMenuItems: [MenuItem] = []

    // Location
    @State private var latitude: Double?
    @State private var longitude: Double?
    @State private var showingMapPicker = false

    // Draft submission (created at the top of Review, so photos have a real ID to attach to
    // before "Submit for review" — see the draft->pending lifecycle change). The fingerprints
    // detect edits made after going Back from Review, so the draft is updated (details) or
    // recreated (location) instead of silently submitting stale data.
    @State private var draftSubmissionId: Int?
    @State private var draftDetailsFingerprint: String?
    @State private var draftLocationFingerprint: String?
    @State private var isCreatingDraft = false
    @State private var draftError: String?

    // Photos
    @State private var photoPickerItems: [PhotosPickerItem] = []
    @State private var uploadedPhotos: [UploadedPhotoState] = []

    // Submit
    @State private var isSubmitting = false
    @State private var submitError: String?
    @State private var createdSubmission: MySubmission?

    private static let categorySuggestions = ["Mamak", "Nasi campur", "Kopitiam", "Cafe", "Western", "Chinese", "Indian", "Warung", "Dessert", "Street food"]

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                if step != .success {
                    progressHeader
                        .padding(.horizontal, 20)
                        .padding(.top, 8)
                        .padding(.bottom, 16)
                }

                Group {
                    switch step {
                    case .search: searchStep
                    case .details: detailsStep
                    case .location: locationStep
                    case .review: reviewStep
                    case .success: successStep
                    }
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .top)
                .transition(stepTransition)
                .id(step)
            }
            .background(Color.nasiCream.ignoresSafeArea())
            .animation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.22), value: step)
            .navigationTitle(step == .success ? "" : navigationTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { toolbarContent }
            .interactiveDismissDisabled(isDirty && step != .success)
            .confirmationDialog("Discard this place?", isPresented: $confirmingDiscard, titleVisibility: .visible) {
                Button("Discard", role: .destructive) { discardAndDismiss() }
                Button("Keep editing", role: .cancel) {}
            } message: {
                Text("What you've filled in so far won't be saved.")
            }
            .sheet(isPresented: $showingMapPicker) {
                MapPinPickerView(initialCoordinate: pinnedCoordinate ?? currentCoordinate) { coordinate in
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.8)) {
                        latitude = coordinate.latitude
                        longitude = coordinate.longitude
                        locationSource = .mapPin
                    }
                    showingMapPicker = false
                }
            }
        }
        .onAppear {
            guard let prefillExisting, restaurantId == nil else { return }
            selectExisting(prefillExisting)
            showingMenuSection = prefillShowMenuSection
        }
    }

    // MARK: - Frame shared by every step

    private var navigationTitle: String {
        submissionType == .editPlace ? "Suggest an edit" : Copy.communitySearchTitle
    }

    @ToolbarContentBuilder
    private var toolbarContent: some ToolbarContent {
        ToolbarItem(placement: .cancellationAction) {
            if step != .success {
                if canGoBack {
                    Button {
                        goBack()
                    } label: {
                        Label("Back", systemImage: "chevron.left")
                            .labelStyle(.titleAndIcon)
                    }
                } else {
                    Button("Cancel") { requestCancel() }
                }
            }
        }
        ToolbarItem(placement: .confirmationAction) {
            if step != .success && canGoBack {
                Button("Cancel") { requestCancel() }
            }
        }
    }

    private var stepTransition: AnyTransition {
        if reduceMotion { return .opacity }
        return .asymmetric(
            insertion: .opacity.combined(with: .move(edge: goingForward ? .trailing : .leading)),
            removal: .opacity
        )
    }

    /// Search only counts as a step when the user actually searched — the "Know the menu?" edit
    /// entry starts at Details, so its progress is 3 steps, not 4 with a skipped first one.
    private var visibleSteps: [AddPlaceStep] {
        prefillExisting == nil ? [.search, .details, .location, .review] : [.details, .location, .review]
    }

    private var currentVisibleIndex: Int {
        visibleSteps.firstIndex(of: step) ?? 0
    }

    private var canGoBack: Bool {
        currentVisibleIndex > 0
    }

    private var progressHeader: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(String(format: Copy.communityStepFormat, currentVisibleIndex + 1, visibleSteps.count, step.title))
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
            HStack(spacing: 6) {
                ForEach(Array(visibleSteps.enumerated()), id: \.offset) { index, _ in
                    Capsule()
                        .fill(index <= currentVisibleIndex ? Color.sambalRed : Color.kicap.opacity(0.12))
                        .frame(height: 4)
                }
            }
            .animation(.easeOut(duration: 0.2), value: step)
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Step \(currentVisibleIndex + 1) of \(visibleSteps.count), \(step.title)")
    }

    private func stepHeading(_ title: String, _ subtitle: String?) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title)
                .font(.makanDisplay(22))
                .foregroundStyle(Color.kicap)
                .accessibilityAddTraits(.isHeader)
            if let subtitle {
                Text(subtitle)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    /// The one pinned action area every step uses.
    private func actionBar(
        _ title: String,
        enabled: Bool = true,
        loading: Bool = false,
        hint: String? = nil,
        secondary: SecondaryAction? = nil,
        action: @escaping () -> Void
    ) -> some View {
        VStack(spacing: 8) {
            if let hint, !enabled {
                Text(hint)
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
                    .transition(.opacity)
            }
            Button {
                UIImpactFeedbackGenerator(style: .medium).impactOccurred()
                action()
            } label: {
                ZStack {
                    Text(title).opacity(loading ? 0 : 1)
                    if loading { ProgressView().tint(.white) }
                }
                .font(.makanBody(16).weight(.semibold))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity, minHeight: 52)
                .background(enabled ? Color.sambalRed : Color.sambalRed.opacity(0.35))
                .clipShape(Capsule())
            }
            .buttonStyle(PressCompressStyle())
            .disabled(!enabled || loading)

            if let secondary {
                Button(secondary.title, action: secondary.action)
                    .font(.makanBody(14))
                    .foregroundStyle(Color.sambalRed)
                    .frame(minHeight: 36)
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 10)
        .padding(.bottom, 8)
        .background(Color.nasiCream.shadow(.drop(color: Color.kicap.opacity(0.06), radius: 8, y: -4)))
    }

    private func go(to next: AddPlaceStep) {
        goingForward = next.rawValue > step.rawValue
        withAnimation { step = next }
    }

    private func goBack() {
        guard canGoBack else { return }
        go(to: visibleSteps[currentVisibleIndex - 1])
    }

    /// Only ask "discard?" when there's real work to lose — opening the edit flow and backing
    /// straight out shouldn't nag.
    private var isDirty: Bool {
        if draftSubmissionId != nil || !uploadedPhotos.isEmpty { return true }
        if submissionType == .editPlace { return !computeChangedFields().isEmpty || !notes.isEmpty }
        return googlePlaceId != nil || !trimmedName.isEmpty
    }

    private func requestCancel() {
        if isDirty {
            confirmingDiscard = true
        } else {
            dismiss()
        }
    }

    /// Cancels any draft already created, so abandoning the flow doesn't leave it for the pruner.
    private func discardAndDismiss() {
        if let draftSubmissionId {
            Task { _ = try? await APIClient.cancelSubmission(id: draftSubmissionId) }
        }
        dismiss()
    }

    // MARK: - Step 1: Find

    private var searchStep: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                stepHeading("Find the place", Copy.communitySearchSubtitle)

                searchField

                if let searchError {
                    InlineMessage(text: searchError, isError: true)
                }

                if let results = searchResults {
                    if !results.existing.isEmpty {
                        resultSection(Copy.communitySearchExistingLabel) {
                            ForEach(results.existing) { place in
                                PlaceResultRow(
                                    title: place.name,
                                    subtitle: [place.foodCategory, place.address].compactMap { $0 }.first,
                                    badge: "Suggest an edit",
                                    systemImage: "pencil",
                                    tint: .kunyit
                                ) { selectExisting(place) }
                            }
                        }
                    }
                    if !results.google.isEmpty {
                        resultSection(Copy.communitySearchGoogleLabel) {
                            ForEach(results.google) { candidate in
                                PlaceResultRow(
                                    title: candidate.name,
                                    subtitle: candidate.foodCategory,
                                    badge: "Add",
                                    systemImage: "plus",
                                    tint: .sambalRed
                                ) { selectGoogleCandidate(candidate) }
                            }
                        }
                    }
                    if results.existing.isEmpty && results.google.isEmpty && !isSearching {
                        Text("No matches for “\(searchQuery.trimmingCharacters(in: .whitespaces))”.")
                            .font(.makanBody(14))
                            .foregroundStyle(.secondary)
                    }
                }

                cantFindCard
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
            .animation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.18), value: searchResults)
        }
        .scrollDismissesKeyboard(.interactively)
        .task(id: searchQuery) { await debouncedSearch() }
        .onAppear { if searchResults == nil { searchFocused = true } }
    }

    private var searchField: some View {
        HStack(spacing: 10) {
            Image(systemName: "magnifyingglass")
                .foregroundStyle(.secondary)
                .accessibilityHidden(true)
            TextField(Copy.communitySearchPlaceholder, text: $searchQuery)
                .font(.makanBody(16))
                .focused($searchFocused)
                .submitLabel(.search)
                .autocorrectionDisabled()
                .onSubmit { Task { await search() } }
            if isSearching {
                ProgressView()
            } else if !searchQuery.isEmpty {
                Button {
                    searchQuery = ""
                    searchResults = nil
                } label: {
                    Image(systemName: "xmark.circle.fill").foregroundStyle(.secondary)
                }
                .accessibilityLabel("Clear search")
            }
        }
        .fieldChrome(isFocused: searchFocused)
    }

    private func resultSection(_ title: String, @ViewBuilder rows: () -> some View) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionLabel(text: title)
            rows()
        }
    }

    private var cantFindCard: some View {
        Button {
            selectManual()
        } label: {
            HStack(alignment: .top, spacing: 12) {
                Image(systemName: "mappin.and.ellipse")
                    .font(.system(size: 20))
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 28)
                VStack(alignment: .leading, spacing: 4) {
                    Text(Copy.communityCantFindHeadline)
                        .font(.makanBody(16))
                        .foregroundStyle(Color.kicap)
                    Text(Copy.communityCantFindDetail)
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                    Text(Copy.communityAddManually)
                        .font(.makanBody(14).weight(.semibold))
                        .foregroundStyle(Color.sambalRed)
                        .padding(.top, 4)
                }
                Spacer(minLength: 0)
            }
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.kunyit.opacity(0.15))
            .clipShape(RoundedRectangle(cornerRadius: 16))
        }
        .buttonStyle(PressCompressStyle())
    }

    /// Search as you type (after a short pause) — the magnifier button needed an extra tap and
    /// wasn't discoverable. `.task(id:)` cancels the previous run on every keystroke.
    private func debouncedSearch() async {
        let term = searchQuery.trimmingCharacters(in: .whitespaces)
        guard term.count >= 2 else {
            if term.isEmpty { searchResults = nil }
            return
        }
        try? await Task.sleep(for: .milliseconds(450))
        guard !Task.isCancelled else { return }
        await search()
    }

    @MainActor
    private func search() async {
        let term = searchQuery.trimmingCharacters(in: .whitespaces)
        guard term.count >= 2 else { return }
        isSearching = true
        searchError = nil
        defer { isSearching = false }
        do {
            let results = try await APIClient.searchCommunityPlaces(
                query: term, latitude: currentCoordinate?.latitude, longitude: currentCoordinate?.longitude
            )
            guard !Task.isCancelled else { return }
            searchResults = results
        } catch {
            guard !Task.isCancelled else { return }
            searchError = "Couldn't search right now. Check your connection and try again."
        }
    }

    private func selectExisting(_ place: ExistingPlaceResult) {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        submissionType = .editPlace
        sourceType = .manual
        googlePlaceId = nil
        restaurantId = place.id
        setFields(
            name: place.name, foodCategory: place.foodCategory ?? "", averageSpend: spendString(for: place.priceLevel),
            address: place.address ?? "", phone: "", instagram: "", tiktok: "", website: "", menu: []
        )
        snapshotOriginals()
        // An edit never moves the place, but the submission still needs its coordinates.
        locationSource = .currentLocation
        latitude = place.latitude
        longitude = place.longitude
        resetDraft()
        go(to: .details)
    }

    private func selectGoogleCandidate(_ candidate: GooglePlaceCandidate) {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        submissionType = .newPlace
        sourceType = .google
        googlePlaceId = candidate.googlePlaceId
        restaurantId = nil
        setFields(
            name: candidate.name, foodCategory: candidate.foodCategory ?? "", averageSpend: spendString(for: candidate.priceLevel),
            address: "", phone: "", instagram: "", tiktok: "", website: "", menu: []
        )
        snapshotOriginals()
        locationSource = .google
        latitude = candidate.latitude
        longitude = candidate.longitude
        resetDraft()
        go(to: .details)
    }

    private func selectManual() {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        submissionType = .newPlace
        sourceType = .manual
        googlePlaceId = nil
        restaurantId = nil
        let typed = searchQuery.trimmingCharacters(in: .whitespaces)
        setFields(name: typed, foodCategory: "", averageSpend: "", address: "", phone: "", instagram: "", tiktok: "", website: "", menu: [])
        snapshotOriginals()
        locationSource = .currentLocation
        latitude = nil
        longitude = nil
        resetDraft()
        go(to: .details)
    }

    private func setFields(name: String, foodCategory: String, averageSpend: String, address: String, phone: String, instagram: String, tiktok: String, website: String, menu: [MenuItem]) {
        self.name = name
        self.foodCategory = foodCategory
        self.averageSpend = averageSpend
        self.address = address
        self.phone = phone
        self.instagramHandle = instagram
        self.tiktokHandle = tiktok
        self.websiteUrl = website
        self.menuItems = menu
        self.notes = ""
        triedContinue = false
        showSpendError = false
    }

    private func snapshotOriginals() {
        originalName = name
        originalFoodCategory = foodCategory
        originalAverageSpend = averageSpend
        originalAddress = address
        originalPhone = phone
        originalInstagramHandle = instagramHandle
        originalTiktokHandle = tiktokHandle
        originalWebsiteUrl = websiteUrl
        originalMenuItems = menuItems
    }

    private func computeChangedFields() -> [String] {
        var changed: [String] = []
        if name != originalName { changed.append("name") }
        if address != originalAddress { changed.append("address") }
        if foodCategory != originalFoodCategory { changed.append("food_category") }
        if averageSpend != originalAverageSpend { changed.append("price_level") }
        if phone != originalPhone { changed.append("phone") }
        if instagramHandle != originalInstagramHandle { changed.append("instagram_handle") }
        if tiktokHandle != originalTiktokHandle { changed.append("tiktok_handle") }
        if websiteUrl != originalWebsiteUrl { changed.append("website_url") }
        if menuItems != originalMenuItems { changed.append("menu_items") }
        return changed
    }

    /// Bucketed for the API (`priceLevel` 1-3), asked as a plain "how much do you spend" number
    /// instead — nobody thinks of a warung in RM/RM²/RM³ tiers, they think in ringgit.
    private var derivedPriceLevel: Int? {
        guard let value = Double(averageSpend), value > 0 else { return nil }
        switch value {
        case ..<15: return 1
        case ..<30: return 2
        default: return 3
        }
    }

    private func spendString(for priceLevel: Int?) -> String {
        switch priceLevel {
        case 1: return "10"
        case 2: return "20"
        case 3: return "35"
        default: return ""
        }
    }

    // MARK: - Step 2: Details

    private var trimmedName: String { name.trimmingCharacters(in: .whitespaces) }

    private var detailsStep: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 24) {
                stepHeading(
                    submissionType == .editPlace ? "What should change?" : Copy.communityDetailsHeadline,
                    submissionType == .editPlace ? "Fix anything that's wrong or missing — only what you change gets reviewed." : "Just the basics. Everything else is optional."
                )

                VStack(alignment: .leading, spacing: 18) {
                    FormField(label: "Place name", isRequired: true, error: triedContinue && trimmedName.isEmpty ? "Add a name so people can find it." : nil) {
                        TextField("e.g. Restoran Nasi Kandar Pelita", text: $name)
                            .textInputAutocapitalization(.words)
                    }

                    VStack(alignment: .leading, spacing: 8) {
                        FormField(label: "Category") {
                            TextField("e.g. Mamak", text: $foodCategory)
                                .textInputAutocapitalization(.words)
                        }
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack(spacing: 8) {
                                ForEach(Self.categorySuggestions, id: \.self) { suggestion in
                                    let selected = foodCategory.caseInsensitiveCompare(suggestion) == .orderedSame
                                    Button {
                                        foodCategory = selected ? "" : suggestion
                                    } label: {
                                        Text(suggestion)
                                            .font(.makanBody(13))
                                            .foregroundStyle(selected ? .white : Color.kicap)
                                            .padding(.horizontal, 12)
                                            .frame(minHeight: 32)
                                            .background(selected ? Color.sambalRed : Color.white)
                                            .overlay(Capsule().stroke(Color.kicap.opacity(selected ? 0 : 0.1), lineWidth: 1))
                                            .clipShape(Capsule())
                                    }
                                    .buttonStyle(.plain)
                                    .accessibilityAddTraits(selected ? .isSelected : [])
                                }
                            }
                        }
                    }

                    FormField(
                        label: "Spend per person",
                        footer: showSpendError ? nil : Copy.communitySpendFooter,
                        error: showSpendError ? Copy.communitySpendErrorInline : nil
                    ) {
                        HStack(spacing: 6) {
                            Text("RM").foregroundStyle(.secondary)
                            TextField("15", text: $averageSpend)
                                .keyboardType(.decimalPad)
                                .onChange(of: averageSpend) { _, _ in showSpendError = false }
                        }
                    }
                }

                VStack(spacing: 12) {
                    DisclosureCard(
                        isExpanded: $showingMoreDetails,
                        systemImage: "info.circle",
                        title: Copy.communityAddMoreDetailsTitle,
                        subtitle: Copy.communityAddMoreDetailsSubtitle,
                        filledCount: [address, phone, instagramHandle, tiktokHandle, websiteUrl].filter { !$0.isEmpty }.count
                    ) {
                        VStack(alignment: .leading, spacing: 16) {
                            FormField(label: "Address") { TextField("Street, area", text: $address) }
                            FormField(label: "Phone") { TextField("012-345 6789", text: $phone).keyboardType(.phonePad) }
                            FormField(label: "Instagram") {
                                TextField("@handle", text: $instagramHandle).textInputAutocapitalization(.never).autocorrectionDisabled()
                            }
                            FormField(label: "TikTok") {
                                TextField("@handle", text: $tiktokHandle).textInputAutocapitalization(.never).autocorrectionDisabled()
                            }
                            FormField(label: "Website") {
                                TextField("https://", text: $websiteUrl).keyboardType(.URL).textInputAutocapitalization(.never).autocorrectionDisabled()
                            }
                            Text(Copy.communityBlankIsFine)
                                .font(.makanBody(12))
                                .foregroundStyle(.secondary)
                        }
                    }

                    DisclosureCard(
                        isExpanded: $showingMenuSection,
                        systemImage: "menucard",
                        title: Copy.communityAddMenuTitle,
                        subtitle: Copy.communityAddMenuSubtitle,
                        filledCount: menuItems.count
                    ) {
                        VStack(alignment: .leading, spacing: 10) {
                            ForEach(menuItems) { item in
                                HStack {
                                    Text(item.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                                    Spacer()
                                    if let price = item.price {
                                        Text("RM\(price, specifier: "%.2f")").font(.makanBody(14)).foregroundStyle(.secondary)
                                    }
                                    Button {
                                        withAnimation(.easeOut(duration: 0.18)) {
                                            menuItems.removeAll { $0.id == item.id }
                                        }
                                    } label: {
                                        Image(systemName: "xmark.circle.fill")
                                            .foregroundStyle(.secondary)
                                            .frame(width: 32, height: 32)
                                    }
                                    .accessibilityLabel("Remove \(item.name)")
                                }
                                .transition(.opacity.combined(with: .move(edge: .top)))
                                Divider()
                            }
                            AddMenuItemRow { newItem in
                                withAnimation(.easeOut(duration: 0.18)) { menuItems.append(newItem) }
                            }
                        }
                    }
                }

                FormField(label: "Notes for the reviewer", footer: "Optional") {
                    TextField("Anything else worth knowing?", text: $notes, axis: .vertical)
                        .lineLimit(2...4)
                }
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .safeAreaInset(edge: .bottom) {
            actionBar("Continue", enabled: !trimmedName.isEmpty, hint: "Add a name to continue") {
                triedContinue = true
                guard !trimmedName.isEmpty else { return }
                if !averageSpend.isEmpty && Double(averageSpend) == nil {
                    withAnimation { showSpendError = true }
                    return
                }
                go(to: .location)
            }
        }
    }

    // MARK: - Step 3: Location

    private var pinnedCoordinate: CLLocationCoordinate2D? {
        guard let latitude, let longitude else { return nil }
        return CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
    }

    private var locationStep: some View {
        let isEdit = submissionType != .newPlace
        let hasPin = pinnedCoordinate != nil

        return ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                stepHeading(
                    isEdit ? "Location stays the same" : (hasPin ? "Is this the right spot?" : "Where is it?"),
                    isEdit ? Copy.communityLocationImmutable : locationSubtitle
                )

                if let coordinate = pinnedCoordinate {
                    LocationPreview(coordinate: coordinate, name: trimmedName)
                    if !isEdit {
                        Label(locationSourceLabel, systemImage: locationSourceIcon)
                            .font(.makanBody(13))
                            .foregroundStyle(.secondary)
                    }
                } else if isEdit {
                    InlineMessage(text: "We couldn't load this place's location. Go back and pick it from search again.", isError: true)
                } else {
                    VStack(spacing: 12) {
                        LocationOptionButton(
                            systemImage: "location.fill",
                            title: Copy.communityUseCurrentLocation,
                            subtitle: "Best if you're at the place right now"
                        ) { useCurrentLocation() }
                        LocationOptionButton(
                            systemImage: "map",
                            title: Copy.communityChooseOnMap,
                            subtitle: "Drag the map to drop a pin"
                        ) { showingMapPicker = true }
                    }
                }
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .safeAreaInset(edge: .bottom) {
            if isEdit || hasPin {
                actionBar(
                    isEdit ? "Continue" : "Looks right",
                    enabled: hasPin,
                    secondary: isEdit ? nil : SecondaryAction(title: sourceType == .google ? "Adjust pin" : "Change location") { showingMapPicker = true }
                ) { go(to: .review) }
            }
        }
    }

    private var locationSubtitle: String {
        if pinnedCoordinate == nil { return "Pin it so people can actually find it." }
        return sourceType == .google ? "This is where Google says the place is." : Copy.communityLocationReadyDetail
    }

    private var locationSourceLabel: String {
        switch locationSource {
        case .google: "Location from Google"
        case .mapPin: "Pinned on the map"
        default: "Your current location"
        }
    }

    private var locationSourceIcon: String {
        switch locationSource {
        case .google: "globe"
        case .mapPin: "mappin"
        default: "location.fill"
        }
    }

    private func useCurrentLocation() {
        guard case .authorized(let coordinate) = locationService.state else {
            locationService.requestLocation()
            return
        }
        withAnimation(.spring(response: 0.3, dampingFraction: 0.8)) {
            latitude = coordinate.latitude
            longitude = coordinate.longitude
            locationSource = .currentLocation
        }
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
    }

    private var currentCoordinate: CLLocationCoordinate2D? {
        if case .authorized(let coordinate) = locationService.state {
            return coordinate
        }
        return nil
    }

    // MARK: - Step 4: Review

    private var reviewStep: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                stepHeading("Looks good?", "Check everything before it goes to review.")

                ReviewCard(title: "Details", onEdit: { go(to: .details) }) {
                    ReviewRow(label: "Name", value: trimmedName)
                    ReviewRow(label: "Category", value: foodCategory.isEmpty ? nil : foodCategory)
                    ReviewRow(label: "Spend", value: averageSpend.isEmpty ? nil : "≈ RM\(averageSpend) / person")
                    ReviewRow(label: "Address", value: address.isEmpty ? nil : address)
                    ReviewRow(label: "Contact", value: [phone, instagramHandle, tiktokHandle, websiteUrl].filter { !$0.isEmpty }.joined(separator: " · ").nilIfEmpty)
                    ReviewRow(label: "Menu", value: menuItems.isEmpty ? nil : "\(menuItems.count) item\(menuItems.count == 1 ? "" : "s")")
                    if submissionType == .editPlace {
                        ReviewRow(label: "Changes", value: computeChangedFields().isEmpty ? "Nothing changed yet" : "\(computeChangedFields().count) field\(computeChangedFields().count == 1 ? "" : "s")")
                    }
                }

                ReviewCard(title: "Location", onEdit: submissionType == .newPlace ? { go(to: .location) } : nil) {
                    if let coordinate = pinnedCoordinate {
                        LocationPreview(coordinate: coordinate, name: trimmedName, height: 120)
                    }
                    Text(submissionType == .newPlace ? locationSourceLabel : "Unchanged")
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                }

                ReviewCard(title: "Photos", subtitle: "Optional · up to 5") {
                    if isCreatingDraft {
                        HStack(spacing: 8) {
                            ProgressView()
                            Text("Getting things ready…").font(.makanBody(13)).foregroundStyle(.secondary)
                        }
                    } else if draftSubmissionId != nil {
                        photosRow
                    } else if let draftError {
                        InlineMessage(text: draftError, isError: true)
                        Button("Try again") { Task { await syncDraft() } }
                            .font(.makanBody(14))
                            .foregroundStyle(Color.sambalRed)
                    }
                }

                if let submitError {
                    InlineMessage(text: submitError, isError: true)
                }
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .safeAreaInset(edge: .bottom) {
            actionBar(
                Copy.communitySubmitForReview,
                enabled: draftSubmissionId != nil && pinnedCoordinate != nil && !uploadedPhotos.contains(where: \.isUploading),
                loading: isSubmitting || isCreatingDraft,
                hint: uploadedPhotos.contains(where: \.isUploading) ? "Waiting for photos to finish uploading…" : nil
            ) {
                Task { await submitForReview() }
            }
        }
        .task { await syncDraft() }
    }

    private var photosRow: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 10) {
                ForEach(uploadedPhotos) { photo in
                    ZStack(alignment: .topTrailing) {
                        Image(uiImage: photo.thumbnail)
                            .resizable()
                            .scaledToFill()
                            .frame(width: 76, height: 76)
                            .clipShape(RoundedRectangle(cornerRadius: 12))
                            .overlay {
                                if photo.isUploading {
                                    RoundedRectangle(cornerRadius: 12).fill(.black.opacity(0.35))
                                    ProgressView().tint(.white)
                                }
                            }
                        if !photo.isUploading {
                            Image(systemName: "checkmark.circle.fill")
                                .foregroundStyle(Color.pandan)
                                .background(Circle().fill(.white))
                                .padding(4)
                        }
                    }
                    .frame(width: 76, height: 76)
                    .transition(.opacity)
                }

                if uploadedPhotos.count < 5 {
                    PhotosPicker(selection: $photoPickerItems, maxSelectionCount: 5 - uploadedPhotos.count, matching: .images) {
                        VStack(spacing: 4) {
                            Image(systemName: "camera.fill").font(.system(size: 18))
                            Text("Add").font(.makanBody(11))
                        }
                        .foregroundStyle(Color.sambalRed)
                        .frame(width: 76, height: 76)
                        .background(Color.sambalRed.opacity(0.08))
                        .clipShape(RoundedRectangle(cornerRadius: 12))
                    }
                    .accessibilityLabel("Add photos")
                }
            }
        }
        .onChange(of: photoPickerItems) { _, newItems in
            Task { await handlePickedPhotos(newItems) }
        }
    }

    @MainActor
    private func handlePickedPhotos(_ items: [PhotosPickerItem]) async {
        guard let submissionId = draftSubmissionId, !items.isEmpty else { return }
        photoPickerItems = []

        for item in items {
            guard let data = try? await item.loadTransferable(type: Data.self), let image = UIImage(data: data) else { continue }
            // Re-encoded to JPEG client-side (resizing if oversized) so HEIC never reaches the
            // backend — the server still independently decodes/re-encodes on receipt regardless.
            let resized = image.resizedIfNeeded(maxDimension: 1600)
            guard let jpegData = resized.jpegData(compressionQuality: 0.85) else { continue }

            let state = UploadedPhotoState(thumbnail: resized, isUploading: true, uploadedId: nil)
            let stateId = state.id
            uploadedPhotos.append(state)

            do {
                let response = try await APIClient.uploadSubmissionPhoto(submissionId: submissionId, jpegData: jpegData, photoType: "other")
                if let index = uploadedPhotos.firstIndex(where: { $0.id == stateId }) {
                    withAnimation(.easeOut(duration: 0.18)) {
                        uploadedPhotos[index].isUploading = false
                        uploadedPhotos[index].uploadedId = response.photo.id
                    }
                    UIImpactFeedbackGenerator(style: .light).impactOccurred()
                }
            } catch {
                uploadedPhotos.removeAll { $0.id == stateId }
            }
        }
    }

    // MARK: - Draft lifecycle

    private var detailsFingerprint: String {
        [trimmedName, address, foodCategory, averageSpend, phone, instagramHandle, tiktokHandle, websiteUrl, notes,
         menuItems.map { "\($0.name)|\($0.price ?? -1)" }.joined(separator: ",")].joined(separator: "¦")
    }

    private var locationFingerprint: String {
        "\(latitude ?? 0),\(longitude ?? 0),\(locationSource.rawValue)"
    }

    private func resetDraft() {
        if let draftSubmissionId {
            Task { _ = try? await APIClient.cancelSubmission(id: draftSubmissionId) }
        }
        draftSubmissionId = nil
        draftDetailsFingerprint = nil
        draftLocationFingerprint = nil
        uploadedPhotos = []
        draftError = nil
        submitError = nil
    }

    /// Creates the draft on first arrival at Review; afterwards keeps it in step with whatever
    /// the user changed after going Back — details are PATCHed, a moved pin recreates it (the
    /// update endpoint can't move a location).
    @MainActor
    private func syncDraft() async {
        if draftSubmissionId != nil && draftLocationFingerprint != locationFingerprint {
            resetDraft()
        }
        if let id = draftSubmissionId {
            guard draftDetailsFingerprint != detailsFingerprint else { return }
            isCreatingDraft = true
            defer { isCreatingDraft = false }
            do {
                _ = try await APIClient.updateSubmission(id: id, UpdateSubmissionRequestBody(
                    name: trimmedName, address: address.nilIfEmpty, foodCategory: foodCategory.nilIfEmpty,
                    priceLevel: derivedPriceLevel, phone: phone.nilIfEmpty, instagramHandle: instagramHandle.nilIfEmpty,
                    tiktokHandle: tiktokHandle.nilIfEmpty, websiteUrl: websiteUrl.nilIfEmpty,
                    menuItems: menuItems.isEmpty ? nil : menuItems, notes: notes.nilIfEmpty, changedFields: computeChangedFields()
                ))
                draftDetailsFingerprint = detailsFingerprint
            } catch {
                draftError = "Couldn't save your changes. Try again in a bit."
            }
            return
        }
        await createDraft()
    }

    @MainActor
    private func createDraft() async {
        guard let latitude, let longitude else { return }
        isCreatingDraft = true
        draftError = nil
        defer { isCreatingDraft = false }

        let body = CreateSubmissionRequestBody(
            submissionType: submissionType, sourceType: sourceType, googlePlaceId: googlePlaceId,
            restaurantId: restaurantId, name: trimmedName,
            address: address.nilIfEmpty, foodCategory: foodCategory.nilIfEmpty,
            priceLevel: derivedPriceLevel, phone: phone.nilIfEmpty,
            instagramHandle: instagramHandle.nilIfEmpty,
            tiktokHandle: tiktokHandle.nilIfEmpty,
            websiteUrl: websiteUrl.nilIfEmpty,
            menuItems: menuItems.isEmpty ? nil : menuItems,
            latitude: latitude, longitude: longitude, locationSource: locationSource,
            notes: notes.nilIfEmpty, changedFields: computeChangedFields()
        )

        do {
            let response = try await APIClient.createSubmission(body)
            draftSubmissionId = response.submission.id
            draftDetailsFingerprint = detailsFingerprint
            draftLocationFingerprint = locationFingerprint
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            draftError = "Couldn't prepare this submission. Try again in a bit."
        }
    }

    @MainActor
    private func submitForReview() async {
        await syncDraft()
        guard let submissionId = draftSubmissionId else { return }
        isSubmitting = true
        submitError = nil
        defer { isSubmitting = false }

        do {
            let response = try await APIClient.submitSubmission(id: submissionId)
            createdSubmission = response.submission
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            go(to: .success)
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            submitError = "Couldn't submit right now. Try again in a bit."
        }
    }

    // MARK: - Done

    private var successStep: some View {
        VStack(spacing: 16) {
            Spacer()
            Image(systemName: "checkmark.circle.fill")
                .font(.system(size: 64))
                .foregroundStyle(Color.pandan)
                .symbolEffect(.bounce, value: step)
                .accessibilityHidden(true)
            Text(submissionType == .editPlace ? "Edit sent for review!" : Copy.communitySubmissionSuccessHeadline)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
            if let createdSubmission {
                Text(createdSubmission.name)
                    .font(.makanBody(16))
                    .foregroundStyle(Color.kicap.opacity(0.8))
            }
            Text(Copy.communitySubmissionSuccessDetail)
                .font(.makanBody(14))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 20)
            Text("You'll get a notification once it's reviewed. Track it anytime in My places.")
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 20)
            Spacer()
        }
        .frame(maxWidth: .infinity)
        .safeAreaInset(edge: .bottom) {
            actionBar(
                "Done",
                secondary: prefillExisting == nil ? SecondaryAction(title: "Add another place", action: startOver) : nil
            ) { dismiss() }
        }
    }

    private func startOver() {
        draftSubmissionId = nil
        draftDetailsFingerprint = nil
        draftLocationFingerprint = nil
        uploadedPhotos = []
        createdSubmission = nil
        searchQuery = ""
        searchResults = nil
        restaurantId = nil
        googlePlaceId = nil
        submissionType = .newPlace
        setFields(name: "", foodCategory: "", averageSpend: "", address: "", phone: "", instagram: "", tiktok: "", website: "", menu: [])
        latitude = nil
        longitude = nil
        showingMoreDetails = false
        showingMenuSection = false
        go(to: .search)
    }
}

// MARK: - Shared pieces

private struct SecondaryAction {
    let title: String
    let action: () -> Void
}

private struct UploadedPhotoState: Identifiable {
    let id = UUID()
    var thumbnail: UIImage
    var isUploading: Bool
    var uploadedId: Int?
}

private extension String {
    var nilIfEmpty: String? {
        let trimmed = trimmingCharacters(in: .whitespacesAndNewlines)
        return trimmed.isEmpty ? nil : trimmed
    }
}

/// One text-field look for the whole flow: white rounded field, hairline border that turns
/// sambal-red when focused or in error.
private struct FieldChrome: ViewModifier {
    var isFocused = false
    var isError = false

    func body(content: Content) -> some View {
        content
            .font(.makanBody(16))
            .padding(.horizontal, 14)
            .frame(minHeight: 48)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .overlay(
                RoundedRectangle(cornerRadius: 12)
                    .stroke(isError ? Color.sambalRed : (isFocused ? Color.sambalRed.opacity(0.6) : Color.kicap.opacity(0.12)), lineWidth: isFocused || isError ? 1.5 : 1)
            )
    }
}

private extension View {
    func fieldChrome(isFocused: Bool = false, isError: Bool = false) -> some View {
        modifier(FieldChrome(isFocused: isFocused, isError: isError))
    }
}

/// Label always visible above the value (a bare "20" loses its meaning once the placeholder
/// is gone), optional required marker, footer or inline error below.
private struct FormField<Field: View>: View {
    let label: String
    var isRequired = false
    var footer: String?
    var error: String?
    @ViewBuilder var field: () -> Field
    @FocusState private var focused: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack(spacing: 2) {
                Text(label)
                    .font(.makanBody(13).weight(.semibold))
                    .foregroundStyle(Color.kicap.opacity(0.8))
                if isRequired {
                    Text("*").foregroundStyle(Color.sambalRed).accessibilityLabel("required")
                }
            }
            field()
                .focused($focused)
                .padding(.vertical, 2)
                .fieldChrome(isFocused: focused, isError: error != nil)
            if let error {
                Label(error, systemImage: "exclamationmark.circle.fill")
                    .font(.makanBody(12))
                    .foregroundStyle(Color.sambalRed)
            } else if let footer {
                Text(footer)
                    .font(.makanBody(12))
                    .foregroundStyle(.secondary)
            }
        }
    }
}

private struct SectionLabel: View {
    let text: String

    var body: some View {
        Text(text.uppercased())
            .font(.makanBody(11).weight(.semibold))
            .foregroundStyle(.secondary)
            .tracking(0.6)
            .accessibilityAddTraits(.isHeader)
    }
}

private struct InlineMessage: View {
    let text: String
    var isError = false

    var body: some View {
        Label(text, systemImage: isError ? "exclamationmark.circle.fill" : "info.circle")
            .font(.makanBody(13))
            .foregroundStyle(isError ? Color.sambalRed : .secondary)
            .frame(maxWidth: .infinity, alignment: .leading)
    }
}

/// Every search result, existing or Google, is the same fully tappable row — only the badge says
/// what tapping does.
private struct PlaceResultRow: View {
    let title: String
    let subtitle: String?
    let badge: String
    let systemImage: String
    let tint: Color
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(title)
                        .font(.makanBody(16))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.leading)
                    if let subtitle {
                        Text(subtitle)
                            .font(.makanBody(13))
                            .foregroundStyle(.secondary)
                            .lineLimit(1)
                    }
                }
                Spacer(minLength: 8)
                Label(badge, systemImage: systemImage)
                    .font(.makanBody(12).weight(.semibold))
                    .foregroundStyle(tint == .kunyit ? Color.kicap : tint)
                    .padding(.horizontal, 10)
                    .frame(minHeight: 28)
                    .background(tint.opacity(0.15))
                    .clipShape(Capsule())
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 14))
        }
        .buttonStyle(PressCompressStyle())
        .accessibilityHint(badge)
    }
}

private struct DisclosureCard<Content: View>: View {
    @Binding var isExpanded: Bool
    let systemImage: String
    let title: String
    let subtitle: String
    var filledCount = 0
    @ViewBuilder var content: () -> Content
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            Button {
                withAnimation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.2)) {
                    isExpanded.toggle()
                }
            } label: {
                HStack(spacing: 12) {
                    Image(systemName: systemImage)
                        .font(.system(size: 18))
                        .foregroundStyle(Color.sambalRed)
                        .frame(width: 24)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(title).font(.makanBody(15)).foregroundStyle(Color.kicap)
                        Text(filledCount > 0 ? "\(filledCount) added" : subtitle)
                            .font(.makanBody(12))
                            .foregroundStyle(filledCount > 0 ? Color.pandan : .secondary)
                    }
                    Spacer()
                    Image(systemName: "chevron.down")
                        .font(.system(size: 13, weight: .semibold))
                        .foregroundStyle(.secondary)
                        .rotationEffect(.degrees(isExpanded ? 180 : 0))
                }
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityAddTraits(.isButton)
            .accessibilityValue(isExpanded ? "Expanded" : "Collapsed")

            if isExpanded {
                content()
                    .transition(reduceMotion ? .opacity : .opacity.combined(with: .move(edge: .top)))
            }
        }
        .padding(16)
        .background(Color.white.opacity(0.7))
        .clipShape(RoundedRectangle(cornerRadius: 16))
    }
}

private struct LocationOptionButton: View {
    let systemImage: String
    let title: String
    let subtitle: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                Image(systemName: systemImage)
                    .font(.system(size: 18))
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 40, height: 40)
                    .background(Color.sambalRed.opacity(0.1))
                    .clipShape(Circle())
                VStack(alignment: .leading, spacing: 2) {
                    Text(title).font(.makanBody(16)).foregroundStyle(Color.kicap)
                    Text(subtitle).font(.makanBody(13)).foregroundStyle(.secondary)
                }
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.system(size: 13, weight: .semibold))
                    .foregroundStyle(.secondary)
            }
            .padding(14)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 16))
        }
        .buttonStyle(PressCompressStyle())
    }
}

/// Static map with the pin — shows *where*, instead of a text card claiming a location is set.
private struct LocationPreview: View {
    let coordinate: CLLocationCoordinate2D
    let name: String
    var height: CGFloat = 180

    var body: some View {
        Map(initialPosition: .region(MKCoordinateRegion(center: coordinate, span: MKCoordinateSpan(latitudeDelta: 0.004, longitudeDelta: 0.004))), interactionModes: []) {
            Marker(name.isEmpty ? "Here" : name, coordinate: coordinate)
                .tint(Color.sambalRed)
        }
        .id("\(coordinate.latitude),\(coordinate.longitude)")
        .frame(height: height)
        .clipShape(RoundedRectangle(cornerRadius: 16))
        .allowsHitTesting(false)
        .accessibilityLabel("Map showing the pinned location")
    }
}

private struct ReviewCard<Content: View>: View {
    let title: String
    var subtitle: String?
    var onEdit: (() -> Void)?
    @ViewBuilder var content: () -> Content

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    SectionLabel(text: title)
                    if let subtitle {
                        Text(subtitle).font(.makanBody(12)).foregroundStyle(.secondary)
                    }
                }
                Spacer()
                if let onEdit {
                    Button("Edit", action: onEdit)
                        .font(.makanBody(14))
                        .foregroundStyle(Color.sambalRed)
                        .frame(minHeight: 32)
                        .accessibilityLabel("Edit \(title.lowercased())")
                }
            }
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 16))
    }
}

private struct ReviewRow: View {
    let label: String
    let value: String?

    var body: some View {
        if let value {
            HStack(alignment: .firstTextBaseline) {
                Text(label)
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                    .frame(width: 76, alignment: .leading)
                Text(value)
                    .font(.makanBody(15))
                    .foregroundStyle(Color.kicap)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            .accessibilityElement(children: .combine)
        }
    }
}

private struct AddMenuItemRow: View {
    let onAdd: (MenuItem) -> Void

    @State private var name = ""
    @State private var price = ""
    @FocusState private var nameFocused: Bool

    var body: some View {
        HStack(spacing: 8) {
            TextField("Dish name", text: $name)
                .focused($nameFocused)
                .fieldChrome(isFocused: nameFocused)
            HStack(spacing: 4) {
                Text("RM").foregroundStyle(.secondary)
                TextField("0.00", text: $price).keyboardType(.decimalPad)
            }
            .frame(width: 96)
            .fieldChrome()
            Button {
                onAdd(MenuItem(name: name.trimmingCharacters(in: .whitespaces), price: Double(price)))
                name = ""
                price = ""
                nameFocused = true
            } label: {
                Image(systemName: "plus.circle.fill")
                    .font(.system(size: 28))
                    .foregroundStyle(name.trimmingCharacters(in: .whitespaces).isEmpty ? Color.kicap.opacity(0.2) : Color.sambalRed)
                    .frame(width: 44, height: 44)
            }
            .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty)
            .accessibilityLabel("Add dish")
        }
    }
}
