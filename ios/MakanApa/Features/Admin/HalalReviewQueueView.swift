import SwiftUI

/// Superadmin halal review queue — pending halal reports, highest review priority first.
/// Same moderation endpoint as Filament; approving "certified" requires the moderator to enter
/// and confirm the certificate details (the backend rejects it otherwise).
struct HalalReviewQueueView: View {
    @State private var reports: [AdminSubmission] = []
    @State private var errorMessage: String?

    var body: some View {
        List {
            if let errorMessage {
                Text(errorMessage).font(.makanBody(13)).foregroundStyle(Color.sambalRed)
            }
            if reports.isEmpty && errorMessage == nil {
                Text("Nothing waiting for review.").font(.makanBody(13)).foregroundStyle(.secondary)
            }
            ForEach(reports) { report in
                NavigationLink {
                    HalalReportReviewView(report: report, onHandled: { Task { await load() } })
                } label: {
                    VStack(alignment: .leading, spacing: 4) {
                        HStack {
                            Text(report.name).font(.makanBody(15)).foregroundStyle(Color.kicap)
                            Spacer()
                            Text("P\(report.halal?.reviewPriority ?? 0)").font(.makanBody(11)).foregroundStyle(.secondary)
                        }
                        Text("Claims \(report.halal?.claim?.pickerLabel ?? "—") · now \(report.halal?.currentStatus?.pickerLabel ?? "—")")
                            .font(.makanBody(12)).foregroundStyle(.secondary)
                        if report.halal?.duplicatePhoto == true {
                            Text("⚠️ Duplicate photo").font(.makanBody(11)).foregroundStyle(Color.sambalRed)
                        }
                    }
                }
            }
        }
        .navigationTitle("Halal Reviews")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
    }

    @MainActor
    private func load() async {
        do {
            reports = try await APIClient.adminListHalalQueue().submissions
            errorMessage = nil
        } catch {
            errorMessage = "Couldn't load halal reports."
        }
    }
}

private struct HalalReportReviewView: View {
    let report: AdminSubmission
    let onHandled: () -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var photos: [AdminSubmissionPhoto] = []
    @State private var resolved: HalalStatus = .certified
    @State private var authority: CertificationAuthority = .jakim
    @State private var certificateNumber = ""
    @State private var expiresAt = Calendar.current.date(byAdding: .year, value: 1, to: .now) ?? .now
    @State private var method = "admin_attestation"
    @State private var confirmedCertified = false
    @State private var recordAuthority = true
    @State private var knowsExpiry = false
    @State private var summary = ""
    @State private var note = ""
    @State private var isProcessing = false
    @State private var errorMessage: String?

    private var canApprove: Bool {
        guard !isProcessing else { return false }
        return resolved != .certified || confirmedCertified
    }

    var body: some View {
        Form {
            Section("Evidence") {
                if let comment = report.halal?.comment {
                    Text("\"\(comment)\"").italic()
                }
                if let badges = report.halal?.aiBadges, !badges.isEmpty {
                    VStack(alignment: .leading, spacing: 4) {
                        Text("AI TRIAGE (ADVISORY)").font(.makanBody(10)).foregroundStyle(.secondary)
                        ForEach(badges, id: \.self) { badge in
                            Text(badge).font(.makanBody(12))
                                .padding(.horizontal, 8).padding(.vertical, 3)
                                .background(Color.kicap.opacity(0.06), in: Capsule())
                        }
                    }
                }
                if let why = report.halal?.priorityExplanation {
                    Text("Priority: \(why)").font(.makanBody(11)).foregroundStyle(.secondary)
                }
                Text("Reporter: \(report.submitter.email ?? "deleted account")").font(.makanBody(12)).foregroundStyle(.secondary)
                ScrollView(.horizontal) {
                    HStack {
                        ForEach(photos) { photo in
                            if let url = URL(string: photo.url) {
                                Link(destination: url) {
                                    RemoteImage(url: url) { Color.kicap.opacity(0.06) }
                                        .frame(width: 140, height: 180)
                                        .clipShape(RoundedRectangle(cornerRadius: 10))
                                        .overlay(alignment: .bottomLeading) {
                                            if photo.photoType == "halal_cert" {
                                                Text("CERT").font(.makanBody(9)).padding(4).background(.thinMaterial, in: Capsule()).padding(4)
                                            }
                                        }
                                }
                            }
                        }
                    }
                }
            }

            Section("Decision") {
                Picker("Evidence supports", selection: $resolved) {
                    ForEach(HalalStatus.claimable) { Text($0.pickerLabel).tag($0) }
                }
            }

            if resolved == .certified {
                Section {
                    Toggle("I confirm this premise holds a valid halal certificate", isOn: $confirmedCertified)
                    Toggle("Record the authority", isOn: $recordAuthority)
                    if recordAuthority {
                        Picker("Authority", selection: $authority) {
                            ForEach(CertificationAuthority.allCases) { Text($0.label).tag($0) }
                        }
                    }
                    TextField("Certificate number (optional)", text: $certificateNumber)
                        .textInputAutocapitalization(.characters)
                    Toggle("I know the expiry date", isOn: $knowsExpiry)
                    if knowsExpiry {
                        DatePicker("Expires", selection: $expiresAt, in: Date.now..., displayedComponents: .date)
                    }
                    Picker("How did you confirm it?", selection: $method) {
                        Text("Confirmed by admin").tag("admin_attestation")
                        Text("Public directory").tag("manual_directory_check")
                        Text("Registry lookup").tag("registry")
                        Text("Certificate photo").tag("document_only")
                    }
                    if let registry = report.halal?.registryUrl, let url = URL(string: registry) {
                        Link("Open authority directory", destination: url)
                    }
                } header: {
                    Text("Certification")
                } footer: {
                    Text("Only the confirmation is required. Without an expiry date, the place stays certified until an admin changes it.")
                }
            }

            Section("Public summary (optional)") {
                TextField("No certificate numbers or personal details", text: $summary, axis: .vertical)
            }

            Section {
                Button(isProcessing ? "Approving…" : "Approve") { Task { await approve() } }
                    .disabled(!canApprove)
                    .tint(Color.pandan)
            }

            Section("Or send back") {
                TextField("Note to reporter", text: $note, axis: .vertical)
                Button("Request more evidence") { Task { await requestChanges() } }
                    .disabled(note.isEmpty || isProcessing)
                Button("Reject", role: .destructive) { Task { await reject() } }
                    .disabled(note.isEmpty || isProcessing)
            }

            if let errorMessage {
                Text(errorMessage).foregroundStyle(Color.sambalRed)
            }
        }
        .navigationTitle(report.name)
        .navigationBarTitleDisplayMode(.inline)
        .task {
            resolved = report.halal?.claim ?? .certified
            authority = report.halal?.certificationAuthority ?? .jakim
            certificateNumber = report.halal?.certificateNumber ?? ""
            photos = (try? await APIClient.adminSubmissionPhotos(id: report.id).photos) ?? []
        }
    }

    @MainActor
    private func approve() async {
        await perform {
            _ = try await APIClient.adminApproveHalalReport(id: report.id, AdminApproveHalalRequestBody(
                resolvedStatus: resolved,
                evidenceSummary: summary.isEmpty ? nil : summary,
                certificate: resolved == .certified ? AdminHalalCertificateBody(
                    confirmed: confirmedCertified,
                    authority: recordAuthority ? authority : nil,
                    certificateNumber: certificateNumber.trimmingCharacters(in: .whitespaces).isEmpty ? nil : certificateNumber.trimmingCharacters(in: .whitespaces),
                    expiresAt: knowsExpiry ? HalalDates.apiDate(expiresAt) : nil,
                    verificationMethod: method
                ) : nil
            ))
        }
    }

    @MainActor
    private func requestChanges() async {
        await perform { _ = try await APIClient.adminRequestChanges(id: report.id, reviewNote: note) }
    }

    @MainActor
    private func reject() async {
        await perform { _ = try await APIClient.adminRejectSubmission(id: report.id, reviewNote: note) }
    }

    @MainActor
    private func perform(_ work: () async throws -> Void) async {
        isProcessing = true
        defer { isProcessing = false }
        do {
            try await work()
            onHandled()
            dismiss()
        } catch {
            errorMessage = "That didn't go through — check the certificate details and try again."
        }
    }
}
