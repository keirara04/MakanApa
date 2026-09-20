import SwiftUI

/// Self-service counterpart to `AdminUsersView`'s `CommunitySelectionList` — always a plain
/// list (not the admin sheet's segmented-until-it-grows switch), since this list is only ever
/// going to grow as more universities are seeded and a list stays the same shape at 3 or 30
/// entries.
struct CommunityAssignmentSheet: View {
    let currentUniversity: String?
    let onSaved: () async -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var loadState: LoadState = .loading
    @State private var selection: String
    @State private var isSaving = false
    @State private var errorMessage: String?

    private enum LoadState: Equatable {
        case loading
        case loaded([UniversityOption])
        case failed
    }

    init(currentUniversity: String?, onSaved: @escaping () async -> Void) {
        self.currentUniversity = currentUniversity
        self.onSaved = onSaved
        _selection = State(initialValue: currentUniversity ?? "Public")
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
                        selection = "Public"
                    } label: {
                        HStack {
                            Text("Public").foregroundStyle(Color.kicap)
                            Spacer()
                            if selection == "Public" {
                                Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                            }
                        }
                    }
                }

                switch loadState {
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
                            Button("Retry") { Task { await load() } }
                        }
                    }
                case .loaded(let universities):
                    Section(Copy.communityUniversitiesSectionHeader) {
                        ForEach(universities) { university in
                            Button {
                                selection = university.shortName
                            } label: {
                                HStack {
                                    VStack(alignment: .leading, spacing: 2) {
                                        Text(university.shortName).foregroundStyle(Color.kicap)
                                        Text(university.name)
                                            .font(.makanBody(12))
                                            .foregroundStyle(.secondary)
                                    }
                                    Spacer()
                                    if selection == university.shortName {
                                        Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                                    }
                                }
                            }
                        }
                    }
                }

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
                            .disabled(loadState == .loading)
                    }
                }
            }
            .task { await load() }
        }
    }

    @MainActor
    private func load() async {
        loadState = .loading
        do {
            let response = try await APIClient.listUniversities()
            loadState = .loaded(response.universities)
        } catch {
            loadState = .failed
        }
    }

    @MainActor
    private func save() async {
        isSaving = true
        defer { isSaving = false }
        let university = selection == "Public" ? nil : selection
        do {
            try await AuthStore.shared.updateCommunity(university: university)
            await onSaved()
            dismiss()
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            errorMessage = "Couldn't save your community. Try again."
        }
    }
}
