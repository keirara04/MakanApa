import SwiftUI

struct CommunityReportSheet: View {
    let post: CommunityPost
    let store: CommunityPostStore
    var onReported: () -> Void = {}

    @Environment(\.dismiss) private var dismiss
    @State private var reason: CommunityReportReason?
    @State private var note = ""
    @State private var isSending = false
    @State private var errorMessage: String?

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    Text(Copy.communityPostsReportDetail)
                        .font(.makanBody(13))
                        .foregroundStyle(.secondary)
                }
                Section("Why are you reporting this?") {
                    ForEach(CommunityReportReason.allCases) { option in
                        Button {
                            reason = option
                        } label: {
                            HStack {
                                Text(option.label).foregroundStyle(Color.kicap)
                                Spacer()
                                if reason == option {
                                    Image(systemName: "checkmark").foregroundStyle(Color.sambalRed)
                                }
                            }
                        }
                        .accessibilityAddTraits(reason == option ? .isSelected : [])
                    }
                }
                Section {
                    TextField(Copy.communityPostsReportNotePlaceholder, text: $note, axis: .vertical)
                        .lineLimit(2...5)
                }
                if let errorMessage {
                    Section {
                        Text(errorMessage).foregroundStyle(Color.sambalRed)
                    }
                }
            }
            .navigationTitle(Copy.communityPostsReportTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    if isSending {
                        ProgressView()
                    } else {
                        Button(Copy.communityPostsReportSubmit) { Task { await send() } }
                            .disabled(reason == nil)
                    }
                }
            }
        }
    }

    private func send() async {
        guard let reason else { return }
        isSending = true
        defer { isSending = false }
        let trimmedNote = note.trimmingCharacters(in: .whitespacesAndNewlines)
        do {
            try await store.report(post, reason: reason, note: trimmedNote.isEmpty ? nil : String(trimmedNote.prefix(500)))
            onReported()
            dismiss()
        } catch let error as APIError {
            errorMessage = error.serverMessage ?? Copy.communityPostsGenericError
        } catch {
            errorMessage = Copy.communityPostsGenericError
        }
    }
}
