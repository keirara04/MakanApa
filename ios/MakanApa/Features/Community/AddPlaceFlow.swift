import CoreLocation
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

    // Details
    @State private var name = ""
    @State private var foodCategory = ""
    @State private var priceLevel: Int?
    @State private var address = ""
    @State private var notes = ""

    // Location
    @State private var latitude: Double?
    @State private var longitude: Double?
    @State private var showingMapPicker = false

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
        HStack(spacing: 6) {
            ForEach(Array(visibleSteps.enumerated()), id: \.offset) { index, s in
                Capsule()
                    .fill(index <= currentVisibleIndex ? Color.sambalRed : Color.kicap.opacity(0.15))
                    .frame(height: 4)
            }
        }
    }

    private var visibleSteps: [AddPlaceStep] {
        [.details, .location, .review]
    }

    private var currentVisibleIndex: Int {
        visibleSteps.firstIndex(of: step) ?? 0
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

                if let results = searchResults {
                    if !results.existing.isEmpty {
                        sectionLabel(Copy.communitySearchExistingLabel)
                        ForEach(results.existing) { place in
                            existingResultRow(place)
                        }
                    }
                    if !results.google.isEmpty {
                        sectionLabel(Copy.communitySearchGoogleLabel)
                        ForEach(results.google) { candidate in
                            googleResultRow(candidate)
                        }
                    }
                }

                cantFindCard
            }
            .opacity(1)
            .transition(.opacity)
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
            selectGoogleCandidate(candidate)
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
        name = place.name
        foodCategory = place.foodCategory ?? ""
        priceLevel = place.priceLevel
        address = ""
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
        name = candidate.name
        foodCategory = candidate.foodCategory ?? ""
        priceLevel = candidate.priceLevel
        address = ""
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
        name = ""
        foodCategory = ""
        priceLevel = nil
        address = ""
        locationSource = .currentLocation
        latitude = nil
        longitude = nil
        withAnimation { step = .details }
    }

    // MARK: - Details step

    private var detailsStep: some View {
        Form {
            Section {
                TextField("Place name", text: $name)
                TextField("Category (e.g. Mamak)", text: $foodCategory)
                Picker("Price", selection: $priceLevel) {
                    Text("RM").tag(1 as Int?)
                    Text("RM²").tag(2 as Int?)
                    Text("RM³").tag(3 as Int?)
                }
                .pickerStyle(.segmented)
                TextField("Address (optional)", text: $address)
                TextField("Notes (optional)", text: $notes)
            }

            Button("Next") {
                withAnimation { step = .location }
            }
            .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty)
        }
        .frame(maxHeight: 420)
    }

    // MARK: - Location step

    private var locationStep: some View {
        VStack(alignment: .leading, spacing: 16) {
            switch submissionType {
            case .editPlace, .closure:
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
        VStack(alignment: .leading, spacing: 16) {
            VStack(alignment: .leading, spacing: 6) {
                Text(name).font(.makanDisplay(18)).foregroundStyle(Color.kicap)
                HStack(spacing: 8) {
                    if !foodCategory.isEmpty { Text(foodCategory) }
                    if let spend = PricePresentation.approximateSpendLabel(for: priceLevel) { Text(spend) }
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

            if let submitError {
                Text(submitError).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }

            Button {
                Task { await submit() }
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
            .disabled(isSubmitting || latitude == nil || longitude == nil)

            Spacer()
        }
    }

    @MainActor
    private func submit() async {
        guard let latitude, let longitude else { return }
        isSubmitting = true
        submitError = nil
        defer { isSubmitting = false }

        let body = CreateSubmissionRequestBody(
            submissionType: submissionType, sourceType: sourceType, googlePlaceId: googlePlaceId,
            restaurantId: restaurantId, name: name.trimmingCharacters(in: .whitespaces),
            address: address.isEmpty ? nil : address, foodCategory: foodCategory.isEmpty ? nil : foodCategory,
            priceLevel: priceLevel, latitude: latitude, longitude: longitude, locationSource: locationSource,
            notes: notes.isEmpty ? nil : notes
        )

        do {
            let response = try await APIClient.createSubmission(body)
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
