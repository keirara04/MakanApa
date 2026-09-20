import CoreLocation
import PhotosUI
import SwiftUI

private enum AddPlaceStep: Int, CaseIterable {
    case search, details, location, review, success
}

struct AddPlaceFlow: View {
    @Environment(LocationService.self) private var locationService
    @Environment(\.dismiss) private var dismiss
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var step: AddPlaceStep = .search

    // Search
    @State private var searchQuery = ""
    @State private var searchResults: PlaceSearchResponse?
    @State private var isSearching = false
    @State private var searchError: String?

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
    @State private var showingAddMenuItem = false
    @State private var showSpendError = false

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
    // before "Submit for review" — see the draft->pending lifecycle change)
    @State private var draftSubmissionId: Int?
    @State private var isCreatingDraft = false
    @State private var draftError: String?

    // Photos
    @State private var photoPickerItems: [PhotosPickerItem] = []
    @State private var uploadedPhotos: [UploadedPhotoState] = []

    // Submit
    @State private var isSubmitting = false
    @State private var submitError: String?
    @State private var createdSubmission: MySubmission?

    var body: some View {
        NavigationStack {
            VStack(spacing: 16) {
                if step != .success {
                    stepIndicator
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
                .transition(reduceMotion ? .opacity : .opacity.combined(with: .move(edge: .trailing)))
            }
            .padding(16)
            .background(Color.nasiCream.ignoresSafeArea())
            .animation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.2), value: step)
            .navigationTitle(step == .success ? "" : Copy.communitySearchTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    if step != .success {
                        Button("Cancel") { dismiss() }
                    }
                }
            }
        }
    }

    // MARK: - Step indicator

    private var stepIndicator: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(String(format: Copy.communityStepFormat, currentVisibleIndex + 1, stepTitle))
                .font(.makanBody(12))
                .foregroundStyle(.secondary)
            HStack(spacing: 6) {
                ForEach(Array(visibleSteps.enumerated()), id: \.offset) { index, s in
                    Capsule()
                        .fill(index <= currentVisibleIndex ? Color.sambalRed : Color.kicap.opacity(0.15))
                        .frame(height: 4)
                        .animation(.easeOut(duration: 0.18), value: step)
                }
            }
        }
    }

    private var visibleSteps: [AddPlaceStep] {
        [.details, .location, .review]
    }

    private var currentVisibleIndex: Int {
        visibleSteps.firstIndex(of: step) ?? 0
    }

    private var stepTitle: String {
        switch step {
        case .details: return "Details"
        case .location: return "Location"
        case .review: return "Review"
        default: return ""
        }
    }

    // MARK: - Search step

    private var searchStep: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                Text(Copy.communitySearchSubtitle)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)

                HStack {
                    TextField(Copy.communitySearchPlaceholder, text: $searchQuery)
                        .textFieldStyle(.roundedBorder)
                        .onSubmit { Task { await search() } }
                    Button {
                        Task { await search() }
                    } label: {
                        if isSearching {
                            ProgressView()
                        } else {
                            Image(systemName: "magnifyingglass")
                        }
                    }
                    .disabled(searchQuery.trimmingCharacters(in: .whitespaces).count < 2 || isSearching)
                }

                if let searchError {
                    Text(searchError).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
                }

                Group {
                    if let results = searchResults {
                        if !results.existing.isEmpty {
                            sectionLabel(Copy.communitySearchExistingLabel)
                            ForEach(results.existing) { place in
                                existingResultRow(place)
                                    .transition(.opacity.combined(with: .move(edge: .top)))
                            }
                        }
                        if !results.google.isEmpty {
                            sectionLabel(Copy.communitySearchGoogleLabel)
                            ForEach(results.google) { candidate in
                                googleResultRow(candidate)
                                    .transition(.opacity.combined(with: .move(edge: .top)))
                            }
                        }
                    }
                }
                .animation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.18), value: searchResults)

                cantFindCard
            }
        }
    }

    private func sectionLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.makanBody(11))
            .foregroundStyle(.secondary)
            .tracking(0.5)
            .padding(.top, 8)
    }

    private func existingResultRow(_ place: ExistingPlaceResult) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(place.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
            if let category = place.foodCategory {
                Text(category).font(.makanBody(12)).foregroundStyle(.secondary)
            }
            Button("Suggest an edit →") {
                selectExisting(place)
            }
            .font(.makanBody(13))
        }
        .padding(12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 14))
    }

    private func googleResultRow(_ candidate: GooglePlaceCandidate) -> some View {
        Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            withAnimation(reduceMotion ? nil : .easeOut(duration: 0.12)) {
                selectGoogleCandidate(candidate)
            }
        } label: {
            VStack(alignment: .leading, spacing: 6) {
                Text(candidate.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                if let category = candidate.foodCategory {
                    Text(category).font(.makanBody(12)).foregroundStyle(.secondary)
                }
            }
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 14))
        }
    }

    private var cantFindCard: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(Copy.communityCantFindHeadline)
                .font(.makanBody(16))
                .foregroundStyle(Color.kicap)
            Text(Copy.communityCantFindDetail)
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
            Button(Copy.communityAddManually) {
                selectManual()
            }
            .font(.makanBody(14))
            .padding(.top, 4)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.kunyit.opacity(0.15))
        .clipShape(RoundedRectangle(cornerRadius: 16))
        .padding(.top, 12)
    }

    @MainActor
    private func search() async {
        isSearching = true
        searchError = nil
        defer { isSearching = false }
        do {
            searchResults = try await APIClient.searchCommunityPlaces(
                query: searchQuery, latitude: currentCoordinate?.latitude, longitude: currentCoordinate?.longitude
            )
        } catch {
            searchError = "Couldn't search right now."
        }
    }

    private func selectExisting(_ place: ExistingPlaceResult) {
        submissionType = .editPlace
        sourceType = .manual
        restaurantId = place.id
        setFields(
            name: place.name, foodCategory: place.foodCategory ?? "", averageSpend: spendString(for: place.priceLevel),
            address: place.address ?? "", phone: "", instagram: "", tiktok: "", website: "", menu: []
        )
        snapshotOriginals()
        locationSource = .currentLocation
        latitude = nil
        longitude = nil
        withAnimation { step = .details }
    }

    private func selectGoogleCandidate(_ candidate: GooglePlaceCandidate) {
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
        withAnimation { step = .details }
    }

    private func selectManual() {
        submissionType = .newPlace
        sourceType = .manual
        googlePlaceId = nil
        restaurantId = nil
        setFields(name: "", foodCategory: "", averageSpend: "", address: "", phone: "", instagram: "", tiktok: "", website: "", menu: [])
        snapshotOriginals()
        locationSource = .currentLocation
        latitude = nil
        longitude = nil
        withAnimation { step = .details }
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

    // MARK: - Details step

    private var detailsStep: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                Text(Copy.communityDetailsHeadline)
                    .font(.makanDisplay(19))
                    .foregroundStyle(Color.kicap)

                VStack(alignment: .leading, spacing: 16) {
                    LabeledTextField(label: "Place name", text: $name)
                    LabeledTextField(label: "Category", text: $foodCategory, placeholder: "e.g. Mamak")
                    VStack(alignment: .leading, spacing: 4) {
                        Text("EXPECTED SPEND / PERSON")
                            .font(.makanBody(11)).foregroundStyle(.secondary).tracking(0.5)
                        HStack(spacing: 6) {
                            Text("RM").font(.makanBody(16)).foregroundStyle(.secondary)
                            TextField("", text: $averageSpend)
                                .keyboardType(.decimalPad)
                                .font(.makanBody(16))
                                .onChange(of: averageSpend) { _, _ in showSpendError = false }
                        }
                        Divider()
                        Text(showSpendError ? Copy.communitySpendErrorInline : Copy.communitySpendFooter)
                            .font(.makanBody(12))
                            .foregroundStyle(showSpendError ? Color.sambalRed : .secondary)
                    }
                }

                disclosureRow(
                    isExpanded: $showingMoreDetails, title: Copy.communityAddMoreDetailsTitle, subtitle: Copy.communityAddMoreDetailsSubtitle
                ) {
                    VStack(alignment: .leading, spacing: 16) {
                        LabeledTextField(label: "Address", text: $address)
                        LabeledTextField(label: "Phone", text: $phone).keyboardType(.phonePad)
                        LabeledTextField(label: "Instagram handle", text: $instagramHandle).textInputAutocapitalization(.never)
                        LabeledTextField(label: "TikTok handle", text: $tiktokHandle).textInputAutocapitalization(.never)
                        LabeledTextField(label: "Website", text: $websiteUrl).keyboardType(.URL).textInputAutocapitalization(.never)
                        Text(Copy.communityBlankIsFine)
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                    }
                }

                disclosureRow(
                    isExpanded: $showingMenuSection, title: Copy.communityAddMenuTitle, subtitle: Copy.communityAddMenuSubtitle
                ) {
                    VStack(alignment: .leading, spacing: 10) {
                        ForEach(menuItems) { item in
                            HStack {
                                Text(item.name).font(.makanBody(14))
                                Spacer()
                                if let price = item.price {
                                    Text("RM\(price, specifier: "%.2f")").font(.makanBody(13)).foregroundStyle(.secondary)
                                }
                                Button {
                                    withAnimation(.easeOut(duration: 0.18)) {
                                        menuItems.removeAll { $0.id == item.id }
                                    }
                                } label: {
                                    Image(systemName: "xmark.circle.fill").foregroundStyle(.secondary)
                                }
                            }
                            .transition(.opacity.combined(with: .move(edge: .top)))
                        }

                        if showingAddMenuItem {
                            AddMenuItemRow { newItem in
                                withAnimation(.easeOut(duration: 0.18)) {
                                    menuItems.append(newItem)
                                    showingAddMenuItem = false
                                }
                            }
                            .transition(.opacity.combined(with: .move(edge: .top)))
                        } else {
                            Button("+ \(Copy.communityAddMenuItemCTA)") {
                                withAnimation(.easeOut(duration: 0.18)) { showingAddMenuItem = true }
                            }
                            .font(.makanBody(14))
                        }
                    }
                }

                LabeledTextField(label: "Notes", text: $notes, placeholder: "Anything else worth knowing?")
            }
            .padding(.bottom, 8)
        }
        .safeAreaInset(edge: .bottom) {
            Button("Continue") {
                if name.trimmingCharacters(in: .whitespaces).isEmpty {
                    return
                }
                if !averageSpend.isEmpty && Double(averageSpend) == nil {
                    withAnimation { showSpendError = true }
                    return
                }
                withAnimation { step = .location }
            }
            .font(.makanBody(15))
            .foregroundStyle(.white)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(name.trimmingCharacters(in: .whitespaces).isEmpty ? Color.sambalRed.opacity(0.4) : Color.sambalRed)
            .clipShape(Capsule())
            .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty)
            .padding(.top, 8)
            .background(Color.nasiCream)
        }
    }

    private func disclosureRow(isExpanded: Binding<Bool>, title: String, subtitle: String, @ViewBuilder content: () -> some View) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Button {
                withAnimation(reduceMotion ? .easeOut(duration: 0.12) : .easeOut(duration: 0.2)) {
                    isExpanded.wrappedValue.toggle()
                }
            } label: {
                HStack {
                    Image(systemName: isExpanded.wrappedValue ? "minus.circle.fill" : "plus.circle.fill")
                        .foregroundStyle(Color.sambalRed)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(title).font(.makanBody(15)).foregroundStyle(Color.kicap)
                        Text(subtitle).font(.makanBody(12)).foregroundStyle(.secondary)
                    }
                    Spacer()
                }
            }

            if isExpanded.wrappedValue {
                content()
                    .transition(reduceMotion ? .opacity : .opacity.combined(with: .move(edge: .top)))
            }
        }
    }

    // MARK: - Location step

    private var locationStep: some View {
        VStack(alignment: .leading, spacing: 16) {
            switch submissionType {
            case .editPlace, .closure, .reopen:
                locationCard(
                    icon: "mappin.circle.fill",
                    title: "📍 Location",
                    detail: Copy.communityLocationImmutable
                )
                nextButton { withAnimation { step = .review } }
            default:
                if sourceType == .google {
                    locationCard(
                        icon: "mappin.circle.fill",
                        title: "📍 Location from Google",
                        detail: "This is where Google says the place is."
                    )
                    Button("Looks right") { withAnimation { step = .review } }
                        .font(.makanBody(14))
                } else {
                    manualLocationChoice
                }
            }
        }
    }

    private func locationCard(icon: String, title: String, detail: String) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title).font(.makanBody(15)).foregroundStyle(Color.kicap)
            Text(detail).font(.makanBody(13)).foregroundStyle(.secondary)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 16))
    }

    private var manualLocationChoice: some View {
        VStack(alignment: .leading, spacing: 12) {
            if latitude != nil && longitude != nil {
                locationCard(icon: "checkmark.circle.fill", title: "✓ Location ready", detail: Copy.communityLocationReadyDetail)
                nextButton { withAnimation { step = .review } }
            } else {
                Button(Copy.communityUseCurrentLocation) {
                    useCurrentLocation()
                }
                .font(.makanBody(15))
                .padding()
                .frame(maxWidth: .infinity)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 16))

                Button(Copy.communityChooseOnMap) {
                    showingMapPicker = true
                }
                .font(.makanBody(15))
                .padding()
                .frame(maxWidth: .infinity)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 16))
            }
        }
        .sheet(isPresented: $showingMapPicker) {
            MapPinPickerView(initialCoordinate: currentCoordinate) { coordinate in
                latitude = coordinate.latitude
                longitude = coordinate.longitude
                locationSource = .mapPin
                showingMapPicker = false
            }
        }
    }

    private func nextButton(_ action: @escaping () -> Void) -> some View {
        Button("Next", action: action)
            .font(.makanBody(15))
    }

    private func useCurrentLocation() {
        guard case .authorized(let coordinate) = locationService.state else {
            locationService.requestLocation()
            return
        }
        withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
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

    // MARK: - Review step

    private var reviewStep: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                VStack(alignment: .leading, spacing: 6) {
                    Text(name).font(.makanDisplay(18)).foregroundStyle(Color.kicap)
                    HStack(spacing: 8) {
                        if !foodCategory.isEmpty { Text(foodCategory) }
                        if !averageSpend.isEmpty { Text("≈ RM\(averageSpend)/person") }
                    }
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                    if !address.isEmpty {
                        Text(address).font(.makanBody(13)).foregroundStyle(.secondary)
                    }
                    Text(sourceType == .google ? "Found on Google" : "Added manually")
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                }
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 16))

                if let draftError {
                    Text(draftError).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
                }

                if isCreatingDraft {
                    HStack {
                        ProgressView()
                        Text("Preparing…").foregroundStyle(.secondary)
                    }
                } else if draftSubmissionId != nil {
                    photosSection
                }

                if let submitError {
                    Text(submitError).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
                }

                Button {
                    Task { await submitForReview() }
                } label: {
                    if isSubmitting {
                        ProgressView()
                    } else {
                        Text(Copy.communitySubmitForReview)
                    }
                }
                .font(.makanBody(15))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 14)
                .background(Color.sambalRed)
                .clipShape(Capsule())
                .disabled(isSubmitting || draftSubmissionId == nil || latitude == nil || longitude == nil)
            }
        }
        .task {
            if draftSubmissionId == nil {
                await createDraft()
            }
        }
    }

    private var photosSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Photos (optional)").font(.makanBody(13)).foregroundStyle(.secondary)
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 10) {
                    ForEach(uploadedPhotos) { photo in
                        ZStack {
                            Image(uiImage: photo.thumbnail)
                                .resizable()
                                .scaledToFill()
                                .frame(width: 72, height: 72)
                                .clipShape(RoundedRectangle(cornerRadius: 12))
                            if photo.isUploading {
                                ProgressView().tint(.white)
                            } else {
                                VStack {
                                    HStack {
                                        Spacer()
                                        Image(systemName: "checkmark.circle.fill")
                                            .foregroundStyle(Color.pandan)
                                            .background(Circle().fill(.white))
                                    }
                                    Spacer()
                                }
                                .padding(4)
                            }
                        }
                        .frame(width: 72, height: 72)
                        .transition(.opacity)
                    }

                    if uploadedPhotos.count < 5 {
                        PhotosPicker(selection: $photoPickerItems, maxSelectionCount: 5 - uploadedPhotos.count, matching: .images) {
                            Image(systemName: "plus")
                                .font(.system(size: 20))
                                .foregroundStyle(.secondary)
                                .frame(width: 72, height: 72)
                                .background(Color.kicap.opacity(0.06))
                                .clipShape(RoundedRectangle(cornerRadius: 12))
                        }
                    }
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

            var state = UploadedPhotoState(thumbnail: resized, isUploading: true, uploadedId: nil)
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

    @MainActor
    private func createDraft() async {
        guard let latitude, let longitude else { return }
        isCreatingDraft = true
        draftError = nil
        defer { isCreatingDraft = false }

        let body = CreateSubmissionRequestBody(
            submissionType: submissionType, sourceType: sourceType, googlePlaceId: googlePlaceId,
            restaurantId: restaurantId, name: name.trimmingCharacters(in: .whitespaces),
            address: address.isEmpty ? nil : address, foodCategory: foodCategory.isEmpty ? nil : foodCategory,
            priceLevel: derivedPriceLevel, phone: phone.isEmpty ? nil : phone,
            instagramHandle: instagramHandle.isEmpty ? nil : instagramHandle,
            tiktokHandle: tiktokHandle.isEmpty ? nil : tiktokHandle,
            websiteUrl: websiteUrl.isEmpty ? nil : websiteUrl,
            menuItems: menuItems.isEmpty ? nil : menuItems,
            latitude: latitude, longitude: longitude, locationSource: locationSource,
            notes: notes.isEmpty ? nil : notes, changedFields: computeChangedFields()
        )

        do {
            let response = try await APIClient.createSubmission(body)
            draftSubmissionId = response.submission.id
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            draftError = "Couldn't prepare this submission. Try again in a bit."
        }
    }

    @MainActor
    private func submitForReview() async {
        guard let submissionId = draftSubmissionId else { return }
        isSubmitting = true
        submitError = nil
        defer { isSubmitting = false }

        do {
            let response = try await APIClient.submitSubmission(id: submissionId)
            createdSubmission = response.submission
            withAnimation { step = .success }
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            submitError = "Couldn't submit right now. Try again in a bit."
        }
    }

    // MARK: - Success step

    private var successStep: some View {
        VStack(spacing: 16) {
            Spacer()
            Image(systemName: "checkmark.circle.fill")
                .font(.system(size: 48))
                .foregroundStyle(Color.pandan)
            Text(Copy.communitySubmissionSuccessHeadline)
                .font(.makanDisplay(20))
                .foregroundStyle(Color.kicap)
            if let createdSubmission {
                Text(createdSubmission.name)
                    .font(.makanBody(15))
                    .foregroundStyle(.secondary)
            }
            Text(Copy.communitySubmissionSuccessDetail)
                .font(.makanBody(13))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            Spacer()
            Button("Done") { dismiss() }
                .font(.makanBody(15))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 14)
                .background(Color.sambalRed)
                .clipShape(Capsule())
        }
        .padding(.horizontal, 16)
    }
}

private struct UploadedPhotoState: Identifiable {
    let id = UUID()
    var thumbnail: UIImage
    var isUploading: Bool
    var uploadedId: Int?
}

/// Label stays visible above the value instead of disappearing once the field has content —
/// the earlier draft relied on placeholder text alone, so a filled-in value (e.g. a bare "20")
/// lost all context about what it represented.
private struct LabeledTextField: View {
    let label: String
    @Binding var text: String
    var placeholder: String = ""

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(label.uppercased())
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
                .tracking(0.5)
            TextField(placeholder, text: $text)
                .font(.makanBody(16))
            Divider()
        }
    }
}

private struct AddMenuItemRow: View {
    let onAdd: (MenuItem) -> Void

    @State private var name = ""
    @State private var price = ""

    var body: some View {
        HStack {
            TextField("Dish name", text: $name)
            TextField("RM", text: $price)
                .keyboardType(.decimalPad)
                .frame(width: 60)
            Button {
                onAdd(MenuItem(name: name, price: Double(price)))
                name = ""
                price = ""
            } label: {
                Image(systemName: "plus.circle.fill")
            }
            .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty)
        }
    }
}

private extension UIImage {
    func resizedIfNeeded(maxDimension: CGFloat) -> UIImage {
        let scale = min(1.0, maxDimension / max(size.width, size.height))
        guard scale < 1.0 else { return self }

        let newSize = CGSize(width: size.width * scale, height: size.height * scale)
        let renderer = UIGraphicsImageRenderer(size: newSize)
        return renderer.image { _ in draw(in: CGRect(origin: .zero, size: newSize)) }
    }
}
