import PhotosUI
import SwiftUI

/// Community halal evidence: claim + comment + photos -> draft -> upload -> submit -> admin
/// review. Nothing here changes a restaurant's status; the backend only ever does that after a
/// moderator approves. A "certified" claim needs a certificate photo (enforced server-side too).
struct HalalReportSheet: View {
    let restaurantId: Int
    let restaurantName: String
    /// The user's resumable report (unsent draft / changes requested) — the server updates it in
    /// place, so the form reopens with what they already gave and knows which photos are attached.
    private let existing: HalalMyReport?

    @Environment(\.dismiss) private var dismiss

    @State private var claim: HalalStatus = .certified
    @State private var comment = ""
    @State private var authority: CertificationAuthority = .jakim
    @State private var certificateNumber = ""
    @State private var knowsExpiry = false
    @State private var expiresAt = Calendar.current.date(byAdding: .year, value: 1, to: .now) ?? .now
    @State private var certPhotoItems: [PhotosPickerItem] = []
    @State private var otherPhotoItems: [PhotosPickerItem] = []
    @State private var phase: Phase = .editing
    /// Photos already attached to the draft — a retry after a failed step skips these instead of
    /// re-uploading them into the server's per-submission photo cap and per-minute throttle.
    @State private var uploaded: Set<UploadedPhoto> = []

    private enum Phase: Equatable {
        case editing, sending(String), done, failed(String)
    }

    private struct UploadedPhoto: Hashable {
        let item: PhotosPickerItem
        let type: String
    }

    private struct UnreadablePhoto: Error {}

    init(restaurantId: Int, restaurantName: String, existing: HalalMyReport? = nil) {
        self.restaurantId = restaurantId
        self.restaurantName = restaurantName
        let resumable = existing?.isResumable == true ? existing : nil
        self.existing = resumable
        guard let resumable else { return }

        if let claim = resumable.claim, HalalStatus.claimable.contains(claim) {
            _claim = State(initialValue: claim)
        }
        _comment = State(initialValue: resumable.comment ?? "")
        _authority = State(initialValue: resumable.certificationAuthority ?? .jakim)
        _certificateNumber = State(initialValue: resumable.certificateNumber ?? "")
        if let raw = resumable.certificateExpiresAt, let date = HalalDates.parse(raw) {
            _knowsExpiry = State(initialValue: true)
            _expiresAt = State(initialValue: date)
        }
    }

    // Photos from an earlier attempt are already on the server and count toward its per-report cap.
    private var attachedPhotos: Int { existing?.photoCount ?? 0 }
    private var attachedCertPhotos: Int { existing?.certPhotoCount ?? 0 }
    private var photoSlots: Int { max(0, (existing?.maxPhotos ?? 5) - attachedPhotos) }
    private var certPhotoLimit: Int { claim == .certified ? min(2, photoSlots) : 0 }
    private var otherPhotoLimit: Int { min(3, photoSlots - certPhotoLimit) }
    private var selectedPhotos: Int { (claim == .certified ? certPhotoItems.count : 0) + otherPhotoItems.count }
    private var tooManyPhotos: Bool { selectedPhotos > photoSlots }

    private var needsCertPhoto: Bool { claim == .certified && certPhotoItems.isEmpty && attachedCertPhotos == 0 }
    private var hasEvidence: Bool {
        !comment.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || selectedPhotos > 0 || attachedPhotos > 0
    }
    private var canSend: Bool { !needsCertPhoto && hasEvidence && !tooManyPhotos && !isSending }
    private var isSending: Bool { if case .sending = phase { true } else { false } }

    var body: some View {
        AccountRequired(feature: "report halal status") { accountContent }
    }

    @ViewBuilder
    private var accountContent: some View {
        NavigationStack {
            Group {
                if phase == .done {
                    doneView
                } else {
                    form
                }
            }
            .navigationTitle("Vouch for this place")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(phase == .done ? "Close" : "Cancel") { dismiss() }
                }
            }
        }
    }

    private var form: some View {
        Form {
            Section {
                Picker("This place is…", selection: $claim) {
                    ForEach(HalalStatus.claimable) { status in
                        Text(status.pickerLabel).tag(status)
                    }
                }
                .pickerStyle(.inline)
                .labelsHidden()
            } header: {
                Text(restaurantName)
            } footer: {
                Text(claimHint)
            }

            if claim == .certified {
                Section {
                    if certPhotoLimit > 0 {
                        PhotosPicker(selection: $certPhotoItems, maxSelectionCount: certPhotoLimit, matching: .images) {
                            Label(certPhotoLabel, systemImage: "checkmark.seal")
                        }
                    } else if attachedCertPhotos > 0 {
                        Label("Certificate photo already attached", systemImage: "checkmark.seal")
                            .foregroundStyle(.secondary)
                    }
                    Picker("Issued by", selection: $authority) {
                        ForEach(CertificationAuthority.allCases) { Text($0.label).tag($0) }
                    }
                    TextField("Certificate number (if readable)", text: $certificateNumber)
                        .textInputAutocapitalization(.characters)
                    Toggle("I can see the expiry date", isOn: $knowsExpiry)
                    if knowsExpiry {
                        DatePicker("Expires", selection: $expiresAt, in: Date.now..., displayedComponents: .date)
                    }
                } header: {
                    Text("Certificate")
                } footer: {
                    Text("Our team checks the certificate against the authority's directory before anything is shown as Halal.")
                }
            }

            Section("How do you know?") {
                TextField(claim == .muslimFriendly ? "e.g. Owner is Muslim, no alcohol on the menu" : "e.g. Cert displayed beside the cashier", text: $comment, axis: .vertical)
                    .lineLimit(3...6)
            }

            Section {
                if otherPhotoLimit > 0 {
                    PhotosPicker(selection: $otherPhotoItems, maxSelectionCount: otherPhotoLimit, matching: .images) {
                        Label(otherPhotoItems.isEmpty ? "Add storefront / menu photos" : "\(otherPhotoItems.count) photo(s) added", systemImage: "camera")
                    }
                }
            } footer: {
                VStack(alignment: .leading, spacing: 4) {
                    if attachedPhotos > 0 {
                        Text("\(attachedPhotos) photo(s) from before are already attached — no need to add them again.")
                    }
                    if tooManyPhotos {
                        Text("That's more photos than fit — \(photoSlots) more can be added to this vouch.")
                            .foregroundStyle(Color.sambalRed)
                    }
                    Text("Your name and comment are shown with your report once approved.")
                }
            }

            Section {
                Button {
                    Task { await send() }
                } label: {
                    HStack {
                        Spacer()
                        if case .sending(let step) = phase {
                            ProgressView()
                            Text(step).padding(.leading, 6)
                        } else {
                            Text("Send vouch for review").bold()
                        }
                        Spacer()
                    }
                }
                .disabled(!canSend)
                .tint(Color.sambalRed)

                if case .failed(let message) = phase {
                    Text(message).font(.makanBody(12)).foregroundStyle(Color.sambalRed)
                }
            }
        }
    }

    private var certPhotoLabel: String {
        if !certPhotoItems.isEmpty { return "\(certPhotoItems.count) certificate photo(s) added" }
        return attachedCertPhotos > 0 ? "Add another certificate photo" : "Add certificate photo (required)"
    }

    private var claimHint: String {
        switch claim {
        case .certified: "Only a valid halal certificate counts — we'll verify it."
        case .muslimFriendly: "No halal certificate, but you know something useful (e.g. Muslim-owned, no pork or alcohol on the menu). Tell us how you know."
        case .nonHalal: "Pork, lard or alcohol served. A photo of the menu helps."
        case .unknown: ""
        }
    }

    private var doneView: some View {
        VStack(spacing: 14) {
            MascotView(mood: .idle, size: 90)
            Text("Thanks for vouching!")
                .font(.makanDisplay(20))
                .foregroundStyle(Color.kicap)
            Text("Our team reviews every vouch before it shows to others. You'll get a notification once it's checked.")
                .font(.makanBody(14))
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }
        .padding(32)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    @MainActor
    private func send() async {
        do {
            phase = .sending("Saving…")
            let trimmedComment = comment.trimmingCharacters(in: .whitespacesAndNewlines)
            let response = try await APIClient.createHalalReport(restaurantId: restaurantId, CreateHalalReportRequestBody(
                claim: claim,
                comment: trimmedComment.isEmpty ? nil : trimmedComment,
                certificationAuthority: claim == .certified ? authority : nil,
                certificateNumber: claim == .certified && !certificateNumber.isEmpty ? certificateNumber : nil,
                certificateExpiresAt: claim == .certified && knowsExpiry ? HalalDates.apiDate(expiresAt) : nil
            ))
            let submissionId = response.submission.id

            let uploads: [UploadedPhoto] =
                ((claim == .certified ? certPhotoItems.map { UploadedPhoto(item: $0, type: "halal_cert") } : [])
                + otherPhotoItems.map { UploadedPhoto(item: $0, type: "storefront") })
                .filter { !uploaded.contains($0) }
            for (index, upload) in uploads.enumerated() {
                phase = .sending("Uploading photo \(index + 1) of \(uploads.count)…")
                // A skipped cert photo would only resurface as a confusing "evidence missing" on submit.
                guard let data = try await upload.item.loadTransferable(type: Data.self),
                      let jpeg = UIImage(data: data)?.resizedIfNeeded(maxDimension: 1600).jpegData(compressionQuality: 0.85)
                else { throw UnreadablePhoto() }
                _ = try await APIClient.uploadSubmissionPhoto(submissionId: submissionId, jpegData: jpeg, photoType: upload.type)
                uploaded.insert(upload)
            }

            phase = .sending("Submitting…")
            _ = try await APIClient.submitSubmission(id: submissionId)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            phase = .done
        } catch let error as APIError {
            if case .unauthorized = error { AuthStore.shared.handleUnauthorized() }
            phase = .failed(Self.message(for: error))
        } catch is UnreadablePhoto {
            phase = .failed("Couldn't read one of your photos. Remove it and pick it again.")
        } catch {
            phase = .failed("Something went wrong. Try again in a bit.")
        }
    }

    /// Only a real transport failure blames the connection — everything else says what happened.
    private static func message(for error: APIError) -> String {
        switch error {
        case .rejected(_, let message):
            message
        case .rateLimited:
            "You've sent a lot of reports — please wait a bit, or for some to be reviewed."
        case .transport:
            "Couldn't send your report. Check your connection and try again."
        case .unauthorized:
            "Please sign in again to send your vouch."
        default:
            "Something went wrong on our side. Try again in a bit."
        }
    }
}
