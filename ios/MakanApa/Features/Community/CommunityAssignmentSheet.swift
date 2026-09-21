import SwiftUI

/// Self-service counterpart to `AdminUsersView`'s `CommunitySelectionList` — always a plain
/// list (not the admin sheet's segmented-until-it-grows switch), since this list is only ever
/// going to grow as more universities/areas are seeded and a list stays the same shape at 3 or
/// 30 entries.
struct CommunityAssignmentSheet: View {
    let currentUniversity: String?
    let currentArea: String?
    let onSaved: () async -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var universityLoadState: LoadState<UniversityOption> = .loading
    @State private var areaLoadState: LoadState<AreaOption> = .loading
    @State private var selection: Selection
    @State private var isSaving = false
    @State private var errorMessage: String?
    @State private var requestSheet: RequestSheetContext?

    /// Exactly one of these is ever true — Public, a specific university, or a specific area.
    private enum Selection: Equatable {
        case pub
        case university(String)
        case area(String)
    }

    private enum LoadState<Option: Equatable>: Equatable {
        case loading
        case loaded([Option])
        case failed
    }

    private struct RequestSheetContext: Identifiable {
        let type: String
        var id: String { type }
    }

    init(currentUniversity: String?, currentArea: String?, onSaved: @escaping () async -> Void) {
        self.currentUniversity = currentUniversity
        self.currentArea = currentArea
        self.onSaved = onSaved
        if let currentUniversity {
            _selection = State(initialValue: .university(currentUniversity))
        } else if let currentArea {
            _selection = State(initialValue: .area(currentArea))
        } else {
            _selection = State(initialValue: .pub)
        }
    }

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    Text(Copy.communityChooseCommunitySubtitle)
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                }

                Section {
                    Button {
                        selection = .pub
                    } label: {
                        HStack {
                            Text("Public").foregroundStyle(Color.kicap)
                            Spacer()
                            if selection == .pub {
                                Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                            }
                        }
                    }
                }

                universitySection
                areaSection

                if let errorMessage {
                    Section {
                        Text(errorMessage).foregroundStyle(Color.sambalRed)
                    }
                }

                Section {
                    Text(Copy.communityChooseCommunityFooter)
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                }
            }
            .navigationTitle(Copy.communityChooseCommunityTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    if isSaving {
                        ProgressView()
                    } else {
                        Button(Copy.communityChooseCommunitySave) { Task { await save() } }
                            .disabled(universityLoadState == .loading || areaLoadState == .loading)
                    }
                }
            }
            .sheet(item: $requestSheet) { context in
                CommunityRequestSheet(type: context.type)
            }
            .task { await loadUniversities() }
            .task { await loadAreas() }
        }
    }

    @ViewBuilder
    private var universitySection: some View {
        switch universityLoadState {
        case .loading:
            Section {
                HStack {
                    ProgressView()
                    Text("Loading universities…").foregroundStyle(.secondary)
                }
            }
        case .failed:
            Section {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Couldn't load universities").foregroundStyle(Color.sambalRed)
                    Button("Retry") { Task { await loadUniversities() } }
                }
            }
        case .loaded(let universities):
            Section(Copy.communityUniversitiesSectionHeader) {
                ForEach(universities) { university in
                    Button {
                        selection = .university(university.shortName)
                    } label: {
                        HStack {
                            VStack(alignment: .leading, spacing: 2) {
                                Text(university.shortName).foregroundStyle(Color.kicap)
                                Text(university.name)
                                    .font(.makanBody(12))
                                    .foregroundStyle(.secondary)
                            }
                            Spacer()
                            if selection == .university(university.shortName) {
                                Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                            }
                        }
                    }
                }
                Button(Copy.communityRequestUniversityRow) {
                    requestSheet = RequestSheetContext(type: "university")
                }
                .font(.makanBody(13))
                .foregroundStyle(Color.sambalRed)
            }
        }
    }

    @ViewBuilder
    private var areaSection: some View {
        switch areaLoadState {
        case .loading:
            Section {
                HStack {
                    ProgressView()
                    Text("Loading areas…").foregroundStyle(.secondary)
                }
            }
        case .failed:
            Section {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Couldn't load areas").foregroundStyle(Color.sambalRed)
                    Button("Retry") { Task { await loadAreas() } }
                }
            }
        case .loaded(let areas):
            Section(Copy.communityAreasSectionHeader) {
                ForEach(areas) { area in
                    Button {
                        selection = .area(area.shortName)
                    } label: {
                        HStack {
                            VStack(alignment: .leading, spacing: 2) {
                                Text(area.shortName).foregroundStyle(Color.kicap)
                                Text(area.name)
                                    .font(.makanBody(12))
                                    .foregroundStyle(.secondary)
                            }
                            Spacer()
                            if selection == .area(area.shortName) {
                                Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                            }
                        }
                    }
                }
                Button(Copy.communityRequestAreaRow) {
                    requestSheet = RequestSheetContext(type: "area")
                }
                .font(.makanBody(13))
                .foregroundStyle(Color.sambalRed)
            }
        }
    }

    @MainActor
    private func loadUniversities() async {
        universityLoadState = .loading
        do {
            let response = try await APIClient.listUniversities()
            universityLoadState = .loaded(response.universities)
        } catch {
            universityLoadState = .failed
        }
    }

    @MainActor
    private func loadAreas() async {
        areaLoadState = .loading
        do {
            let response = try await APIClient.listAreas()
            areaLoadState = .loaded(response.areas)
        } catch {
            areaLoadState = .failed
        }
    }

    @MainActor
    private func save() async {
        isSaving = true
        defer { isSaving = false }
        let university: String?
        let area: String?
        switch selection {
        case .pub:
            university = nil
            area = nil
        case .university(let shortName):
            university = shortName
            area = nil
        case .area(let shortName):
            university = nil
            area = shortName
        }
        do {
            try await AuthStore.shared.updateCommunity(university: university, area: area)
            await onSaved()
            dismiss()
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't save your community. Try again."
        }
    }
}

/// Small standalone sheet for "my university/area isn't listed" — deliberately separate from
/// the selection flow above: submitting a request never selects anything, it just tells admins
/// what's missing.
private struct CommunityRequestSheet: View {
    let type: String

    @Environment(\.dismiss) private var dismiss
    @State private var name = ""
    @State private var isSubmitting = false
    @State private var errorMessage: String?
    @State private var didSubmit = false

    var body: some View {
        NavigationStack {
            Form {
                if didSubmit {
                    Section {
                        Text(Copy.communityRequestSentConfirmation)
                            .foregroundStyle(Color.kicap)
                    }
                } else {
                    Section {
                        TextField(placeholder, text: $name)
                    }
                    if let errorMessage {
                        Text(errorMessage).foregroundStyle(Color.sambalRed)
                    }
                }
            }
            .navigationTitle(Copy.communityRequestSheetTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                if !didSubmit {
                    ToolbarItem(placement: .confirmationAction) {
                        if isSubmitting {
                            ProgressView()
                        } else {
                            Button(Copy.communityRequestSubmit) { Task { await submit() } }
                                .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty)
                        }
                    }
                }
            }
        }
    }

    private var placeholder: String {
        type == "university" ? Copy.communityRequestUniversityPlaceholder : Copy.communityRequestAreaPlaceholder
    }

    @MainActor
    private func submit() async {
        isSubmitting = true
        defer { isSubmitting = false }
        do {
            _ = try await APIClient.submitCommunityRequest(type: type, name: name.trimmingCharacters(in: .whitespaces))
            didSubmit = true
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't send your request. Try again."
        }
    }
}
