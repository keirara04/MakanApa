import PhotosUI
import SwiftUI

/// "Own this place?" — ownership proof goes to admin review. Verified owners' halal evidence is
/// tagged as coming from the owner and reviewed faster, but still reviewed.
struct OwnerClaimSheet: View {
    let restaurantId: Int
    let restaurantName: String

    @Environment(\.dismiss) private var dismiss
    @State private var phone = ""
    @State private var notes = ""
    @State private var proofItems: [PhotosPickerItem] = []
    @State private var isSending = false
    @State private var errorMessage: String?
    @State private var isDone = false
    /// Proof already attached to the claim draft — a retry skips these instead of uploading twice.
    @State private var uploaded: Set<PhotosPickerItem> = []

    private struct UnreadablePhoto: Error {}

    var body: some View {
        AccountRequired(feature: "claim this place") { accountContent }
    }

    @ViewBuilder
    private var accountContent: some View {
        NavigationStack {
            Form {
                if isDone {
                    Section {
                        Text("Thanks — our team will verify your ownership and get back to you.")
                    }
                } else {
                    Section {
                        TextField("Contact phone", text: $phone)
                            .keyboardType(.phonePad)
                        TextField("Anything we should know? (optional)", text: $notes, axis: .vertical)
                    } header: {
                        Text(restaurantName)
                    }
                    Section {
                        PhotosPicker(selection: $proofItems, maxSelectionCount: 2, matching: .images) {
                            Label(proofItems.isEmpty ? "Add proof photo (required)" : "\(proofItems.count) photo(s) added", systemImage: "doc.text.image")
                        }
                    } footer: {
                        Text("Business licence (SSM/premise licence) or a photo of you at the signboard. Proof photos stay private — only our team sees them.")
                    }
                    Section {
                        Button(isSending ? "Sending…" : "Send claim") { Task { await send() } }
                            .disabled(isSending || proofItems.isEmpty || phone.trimmingCharacters(in: .whitespaces).isEmpty)
                        if let errorMessage {
                            Text(errorMessage).font(.makanBody(12)).foregroundStyle(Color.sambalRed)
                        }
                    }
                }
            }
            .navigationTitle("Claim this place")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button(isDone ? "Close" : "Cancel") { dismiss() } }
            }
        }
    }

    @MainActor
    private func send() async {
        isSending = true
        defer { isSending = false }
        do {
            let claim = try await APIClient.createOwnerClaim(restaurantId: restaurantId, CreateOwnerClaimRequestBody(
                contactPhone: phone.trimmingCharacters(in: .whitespaces),
                notes: notes.isEmpty ? nil : notes
            ))
            for item in proofItems where !uploaded.contains(item) {
                guard let data = try await item.loadTransferable(type: Data.self),
                      let jpeg = UIImage(data: data)?.resizedIfNeeded(maxDimension: 1600).jpegData(compressionQuality: 0.85)
                else { throw UnreadablePhoto() }
                _ = try await APIClient.uploadSubmissionPhoto(submissionId: claim.submission.id, jpegData: jpeg, photoType: "other")
                uploaded.insert(item)
            }
            _ = try await APIClient.submitSubmission(id: claim.submission.id)
            isDone = true
        } catch APIError.server(let status) where status == 422 {
            errorMessage = "You already have a claim open for this place, or you're already its owner."
        } catch APIError.transport {
            errorMessage = "Couldn't send your claim. Check your connection and try again."
        } catch is UnreadablePhoto {
            errorMessage = "Couldn't read one of your photos. Remove it and pick it again."
        } catch {
            errorMessage = (error as? APIError)?.serverMessage ?? "Couldn't send your claim. Try again."
        }
    }
}
