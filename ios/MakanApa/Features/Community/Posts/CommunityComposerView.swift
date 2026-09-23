import SwiftUI

/// New post or reply. A reply (non-nil `parent`) can't tag a place — the backend rejects it
/// too, this just doesn't offer it.
struct CommunityComposerView: View {
    let store: CommunityPostStore
    let parent: CommunityPost?
    var onPosted: (CommunityPost) -> Void = { _ in }

    @Environment(\.dismiss) private var dismiss
    @State private var text = ""
    @State private var taggedPlace: ExistingPlaceResult?
    @State private var showingPlacePicker = false
    @State private var isPosting = false
    @State private var errorMessage: String?
    @FocusState private var isFocused: Bool

    private let maxLength = 280

    private var trimmed: String { text.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canSubmit: Bool { !trimmed.isEmpty && text.count <= maxLength && !isPosting }

    var body: some View {
        NavigationStack {
            VStack(alignment: .leading, spacing: 12) {
                if let parent {
                    HStack(alignment: .top, spacing: 8) {
                        Rectangle().fill(Color.kicap.opacity(0.15)).frame(width: 2)
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Replying to \(parent.author.name)")
                                .font(.makanBody(12))
                                .foregroundStyle(.secondary)
                            Text(parent.body)
                                .font(.makanBody(13))
                                .foregroundStyle(Color.kicap.opacity(0.8))
                                .lineLimit(3)
                        }
                    }
                    .fixedSize(horizontal: false, vertical: true)
                }

                ZStack(alignment: .topLeading) {
                    if text.isEmpty {
                        Text(parent == nil ? Copy.communityPostsComposePlaceholder : Copy.communityPostsReplyPlaceholder)
                            .font(.makanBody(16))
                            .foregroundStyle(.secondary)
                            .padding(.top, 8)
                            .padding(.leading, 5)
                            .allowsHitTesting(false)
                    }
                    TextEditor(text: $text)
                        .font(.makanBody(16))
                        .foregroundStyle(Color.kicap)
                        .scrollContentBackground(.hidden)
                        .focused($isFocused)
                        .frame(minHeight: 120)
                        .accessibilityLabel(parent == nil ? "Post text" : "Reply text")
                }

                if parent == nil {
                    tagRow
                }

                if let errorMessage {
                    Label(errorMessage, systemImage: "exclamationmark.circle.fill")
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .transition(.opacity)
                }

                HStack {
                    Text(Copy.communityPostsGuidelines)
                        .font(.makanBody(11))
                        .foregroundStyle(.secondary)
                    Spacer()
                    Text("\(maxLength - text.count)")
                        .font(.makanBody(13).monospacedDigit())
                        .foregroundStyle(text.count > maxLength ? Color.sambalRed : text.count > maxLength - 20 ? Color.kunyit : .secondary)
                        .accessibilityLabel("\(maxLength - text.count) characters left")
                }

                Spacer()
            }
            .padding(16)
            .background(Color.nasiCream.ignoresSafeArea())
            .navigationTitle(parent == nil ? "New post" : "Reply")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    if isPosting {
                        ProgressView()
                    } else {
                        Button(parent == nil ? Copy.communityPostsPost : Copy.communityPostsReply) {
                            Task { await submit() }
                        }
                        .fontWeight(.semibold)
                        .disabled(!canSubmit)
                    }
                }
            }
            .sheet(isPresented: $showingPlacePicker) {
                CommunityPlaceTagPicker { place in
                    taggedPlace = place
                }
            }
            .onAppear { isFocused = true }
            .interactiveDismissDisabled(!trimmed.isEmpty)
        }
    }

    @ViewBuilder
    private var tagRow: some View {
        if let taggedPlace {
            HStack(spacing: 6) {
                Image(systemName: "mappin.circle.fill").foregroundStyle(Color.sambalRed)
                Text(taggedPlace.name).foregroundStyle(Color.kicap).lineLimit(1)
                Button {
                    self.taggedPlace = nil
                } label: {
                    Image(systemName: "xmark.circle.fill").foregroundStyle(.secondary)
                }
                .accessibilityLabel("Remove tagged place")
            }
            .font(.makanBody(14))
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(Color.white)
            .clipShape(Capsule())
        } else {
            Button {
                showingPlacePicker = true
            } label: {
                Label(Copy.communityPostsTagPlace, systemImage: "mappin.and.ellipse")
                    .font(.makanBody(14))
                    .foregroundStyle(Color.sambalRed)
                    .padding(.horizontal, 12)
                    .frame(minHeight: 36)
                    .background(Color.sambalRed.opacity(0.08))
                    .clipShape(Capsule())
            }
            .buttonStyle(.plain)
        }
    }

    private func submit() async {
        guard canSubmit else { return }
        isPosting = true
        errorMessage = nil
        defer { isPosting = false }

        do {
            let post = try await store.create(body: trimmed, restaurantId: taggedPlace?.id, parentId: parent?.id)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            onPosted(post)
            dismiss()
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch let error as APIError {
            withAnimation { errorMessage = error.serverMessage ?? Copy.communityPostsGenericError }
        } catch {
            withAnimation { errorMessage = Copy.communityPostsGenericError }
        }
    }
}

/// Only places already on MakanApa can be tagged (they need a restaurant id) — Google-only
/// results from the same search endpoint are deliberately not offered here.
struct CommunityPlaceTagPicker: View {
    let onPick: (ExistingPlaceResult) -> Void

    @Environment(\.dismiss) private var dismiss
    @Environment(LocationService.self) private var locationService
    @State private var query = ""
    @State private var results: [ExistingPlaceResult] = []
    @State private var isSearching = false

    var body: some View {
        NavigationStack {
            List {
                if isSearching {
                    HStack { Spacer(); ProgressView(); Spacer() }
                        .listRowBackground(Color.clear)
                } else if results.isEmpty && query.count >= 2 {
                    Text("No places on MakanApa match that yet.")
                        .font(.makanBody(14))
                        .foregroundStyle(.secondary)
                        .listRowBackground(Color.clear)
                }
                ForEach(results) { place in
                    Button {
                        onPick(place)
                        dismiss()
                    } label: {
                        VStack(alignment: .leading, spacing: 2) {
                            Text(place.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                            if let subtitle = place.foodCategory ?? place.address {
                                Text(subtitle).font(.makanBody(12)).foregroundStyle(.secondary).lineLimit(1)
                            }
                        }
                    }
                }
            }
            .scrollContentBackground(.hidden)
            .background(Color.nasiCream.ignoresSafeArea())
            .searchable(text: $query, placement: .navigationBarDrawer(displayMode: .always), prompt: Copy.communityPostsTagPlaceSearch)
            .navigationTitle(Copy.communityPostsTagPlace)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
            }
            .task(id: query) { await search() }
        }
    }

    private func search() async {
        let term = query.trimmingCharacters(in: .whitespaces)
        guard term.count >= 2 else {
            results = []
            return
        }
        // Debounce: .task(id:) cancels this on the next keystroke, so only a pause searches.
        try? await Task.sleep(for: .milliseconds(400))
        guard !Task.isCancelled else { return }

        isSearching = true
        defer { isSearching = false }
        var coordinate: (Double, Double)?
        if case .authorized(let c) = locationService.state { coordinate = (c.latitude, c.longitude) }
        let response = try? await APIClient.searchCommunityPlaces(query: term, latitude: coordinate?.0, longitude: coordinate?.1)
        guard !Task.isCancelled else { return }
        results = response?.existing ?? []
    }
}
