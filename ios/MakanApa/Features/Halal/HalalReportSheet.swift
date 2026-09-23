import PhotosUI
import SwiftUI

/// Community halal evidence: claim + comment + photos -> draft -> upload -> submit -> admin
/// review. Nothing here changes a restaurant's status; the backend only ever does that after a
/// moderator approves. A "certified" claim needs a certificate photo (enforced server-side too).
struct HalalReportSheet: View {
    let restaurantId: Int
    let restaurantName: String

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

    private enum Phase: Equatable {
        case editing, sending(String), done, failed(String)
    }

    private var needsCertPhoto: Bool { claim == .certified && certPhotoItems.isEmpty }
    private var hasEvidence: Bool {
        !comment.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || !certPhotoItems.isEmpty || !otherPhotoItems.isEmpty
    }
    private var canSend: Bool { !needsCertPhoto && hasEvidence && !isSending }
    private var isSending: Bool { if case .sending = phase { true } else { false } }

    var body: some View {
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
                    PhotosPicker(selection: $certPhotoItems, maxSelectionCount: 2, matching: .images) {
                        Label(certPhotoItems.isEmpty ? "Add certificate photo (required)" : "\(certPhotoItems.count) certificate photo(s) added",
                              systemImage: "checkmark.seal")
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
                PhotosPicker(selection: $otherPhotoItems, maxSelectionCount: 3, matching: .images) {
                    Label(otherPhotoItems.isEmpty ? "Add storefront / menu photos" : "\(otherPhotoItems.count) photo(s) added", systemImage: "camera")
                }
            } footer: {
                Text("Your name and comment are shown with your report once approved.")
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

    private var claimHint: String {
        switch claim {
        case .certified: "Only a valid halal certificate counts — we'll verify it."
        case .muslimFriendly: "Muslim-owned, or no pork/lard/alcohol — but no halal certificate. Tell us how you know."
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

            let uploads: [(PhotosPickerItem, String)] =
                (claim == .certified ? certPhotoItems.map { ($0, "halal_cert") } : [])
                + otherPhotoItems.map { ($0, "storefront") }
            for (index, (item, type)) in uploads.enumerated() {
                phase = .sending("Uploading photo \(index + 1) of \(uploads.count)…")
                guard let data = try await item.loadTransferable(type: Data.self),
                      let jpeg = UIImage(data: data)?.resizedIfNeeded(maxDimension: 1600).jpegData(compressionQuality: 0.85)
                else { continue }
                _ = try await APIClient.uploadSubmissionPhoto(submissionId: submissionId, jpegData: jpeg, photoType: type)
            }

            phase = .sending("Submitting…")
            _ = try await APIClient.submitSubmission(id: submissionId)
            UINotificationFeedbackGenerator().notificationOccurred(.success)
            phase = .done
        } catch APIError.server(let status) where status == 422 {
            phase = .failed("You already have a report waiting for review here, or evidence is missing.")
        } catch APIError.server(let status) where status == 429 {
            phase = .failed("You've sent a lot of reports — please wait for some to be reviewed.")
        } catch {
            phase = .failed("Couldn't send your report. Check your connection and try again.")
        }
    }
}
