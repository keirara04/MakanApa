import SwiftUI

/// Everything the user has added or fixed, and where each one is in review. A summary up top
/// (live / in review / needs changes), a filter, then one card per submission: what kind it is,
/// when it was sent, a status pill, a Sent → Reviewing → Live track, and the reviewer's note when
/// there is one. A pending submission can be withdrawn from its context menu.
struct MySubmissionsView: View {
    @State private var submissions: [MySubmission] = []
    @State private var hasLoaded = false
    @State private var loadFailed = false
    @State private var filter: Filter = .all
    @State private var withdrawing: MySubmission?
    @State private var withdrawError: String?
    @State private var showingAddPlace = false

    private enum Filter: CaseIterable, Hashable {
        case all, review, live

        var title: String {
            switch self {
            case .all: Copy.myPlacesFilterAll
            case .review: Copy.myPlacesStatReview
            case .live: Copy.myPlacesStatLive
            }
        }
    }

    var body: some View {
        AccountRequired(feature: "see places you've added", requiresTerms: false) { accountContent }
    }

    private var accountContent: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                if !hasLoaded {
                    skeleton
                } else if loadFailed && submissions.isEmpty {
                    loadError
                } else if submissions.isEmpty {
                    emptyState
                } else {
                    summary
                    Picker(Copy.myPlacesFilterLabel, selection: $filter) {
                        ForEach(Filter.allCases, id: \.self) { Text($0.title).tag($0) }
                    }
                    .pickerStyle(.segmented)

                    if let withdrawError {
                        Label(withdrawError, systemImage: "exclamationmark.circle.fill")
                            .font(.makanBody(13))
                            .foregroundStyle(Color.sambalRed)
                    }

                    if filtered.isEmpty {
                        Text(Copy.myPlacesEmptyFiltered)
                            .font(.makanBody(14))
                            .foregroundStyle(Color.kicapSecondary)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 24)
                    } else {
                        LazyVStack(spacing: 12) {
                            ForEach(filtered) { submission in
                                SubmissionCard(submission: submission)
                                    .contextMenu {
                                        if submission.status == "pending" {
                                            Button(Copy.myPlacesWithdraw, systemImage: "arrow.uturn.backward", role: .destructive) {
                                                withdrawing = submission
                                            }
                                        }
                                    }
                            }
                        }
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.top, 12)
            .padding(.bottom, 32)
            .animation(Motion.standard, value: filter)
        }
        .background(Color.nasiCream.ignoresSafeArea())
        .navigationTitle(Copy.communityMyPlacesTitle)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
        .sensoryFeedback(.selection, trigger: filter)
        .confirmationDialog(Copy.myPlacesWithdrawTitle, isPresented: Binding(
            get: { withdrawing != nil },
            set: { if !$0 { withdrawing = nil } }
        ), titleVisibility: .visible, presenting: withdrawing) { submission in
            Button(Copy.myPlacesWithdraw, role: .destructive) { Task { await withdraw(submission) } }
        } message: { _ in
            Text(Copy.myPlacesWithdrawMessage)
        }
        .sheet(isPresented: $showingAddPlace, onDismiss: { Task { await load() } }) {
            AddPlaceFlow()
        }
    }

    private var filtered: [MySubmission] {
        switch filter {
        case .all: submissions
        case .review: submissions.filter { $0.status == "pending" || $0.status == "changes_requested" }
        case .live: submissions.filter { $0.status == "approved" }
        }
    }

    // MARK: - Summary

    private var summary: some View {
        HStack(spacing: 10) {
            StatTile(count: submissions.filter { $0.status == "approved" }.count, label: Copy.myPlacesStatLive, tint: .pandan, systemImage: "checkmark.seal.fill")
            StatTile(count: submissions.filter { $0.status == "pending" }.count, label: Copy.myPlacesStatReview, tint: .kunyit, systemImage: "clock.fill")
            StatTile(count: submissions.filter { $0.status == "changes_requested" }.count, label: Copy.myPlacesStatChanges, tint: .sambalRed, systemImage: "exclamationmark.bubble.fill")
        }
    }

    // MARK: - States

    private var skeleton: some View {
        VStack(spacing: 12) {
            HStack(spacing: 10) {
                ForEach(0..<3, id: \.self) { _ in
                    RoundedRectangle.row.fill(Color.hairline).frame(height: 76)
                }
            }
            ForEach(0..<3, id: \.self) { _ in
                RoundedRectangle.card.fill(Color.hairline).frame(height: 120)
            }
        }
        .redacted(reason: .placeholder)
        .accessibilityHidden(true)
    }

    private var emptyState: some View {
        VStack(spacing: 14) {
            MascotView(mood: .idle, size: 96)
            Text(Copy.myPlacesEmptyTitle)
                .font(.makanDisplay(20))
                .foregroundStyle(Color.kicap)
            Text(Copy.myPlacesEmptyDetail)
                .font(.makanBody(14))
                .foregroundStyle(Color.kicapSecondary)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
            Button {
                showingAddPlace = true
            } label: {
                Label(Copy.myPlacesAddPlace, systemImage: "plus")
                    .font(.makanBody(15).weight(.semibold))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 24)
                    .frame(minHeight: 48)
                    .background(Color.sambalRed, in: Capsule())
            }
            .buttonStyle(PressCompressStyle())
            .padding(.top, 4)
        }
        .frame(maxWidth: .infinity)
        .padding(.top, 48)
        .padding(.horizontal, 16)
    }

    private var loadError: some View {
        VStack(spacing: 12) {
            Label(Copy.myPlacesLoadFailed, systemImage: "exclamationmark.circle.fill")
                .font(.makanBody(14))
                .foregroundStyle(Color.sambalRed)
            Button(Copy.tryAgain) { Task { await load() } }
                .font(.makanBody(15).weight(.semibold))
                .foregroundStyle(Color.sambalRed)
                .frame(minHeight: 44)
        }
        .frame(maxWidth: .infinity)
        .padding(.top, 48)
    }

    // MARK: - Data

    @MainActor
    private func load() async {
        do {
            let response = try await APIClient.mySubmissions()
            withAnimation(Motion.standard) {
                submissions = response.submissions
                loadFailed = false
                hasLoaded = true
            }
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            loadFailed = true
            hasLoaded = true
        }
    }

    @MainActor
    private func withdraw(_ submission: MySubmission) async {
        withdrawError = nil
        do {
            _ = try await APIClient.cancelSubmission(id: submission.id)
            await load()
        } catch {
            withdrawError = Copy.myPlacesWithdrawFailed
        }
    }
}

// MARK: - Pieces

private struct StatTile: View {
    let count: Int
    let label: String
    let tint: Color
    let systemImage: String

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Image(systemName: systemImage)
                .font(.subheadline)
                .foregroundStyle(tint)
            Text(count, format: .number)
                .font(.makanDisplay(24))
                .foregroundStyle(Color.kicap)
                .monospacedDigit()
                .contentTransition(.numericText())
            Text(label)
                .font(.makanBody(12))
                .foregroundStyle(Color.kicapSecondary)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12)
        .background(Color.surface, in: .row)
        .overlay(RoundedRectangle.row.strokeBorder(Color.hairline, lineWidth: 1))
        .accessibilityElement(children: .combine)
    }
}

private struct SubmissionCard: View {
    let submission: MySubmission

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(alignment: .top, spacing: 12) {
                Image(systemName: typeSymbol)
                    .font(.headline)
                    .foregroundStyle(Color.sambalRed)
                    .frame(width: 40, height: 40)
                    .background(Color.sambalRed.opacity(0.1), in: Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 3) {
                    Text(submission.name)
                        .font(.makanBody(16).weight(.semibold))
                        .foregroundStyle(Color.kicap)
                        .fixedSize(horizontal: false, vertical: true)
                    Text([typeLabel, detailLine, sentAgo].compactMap { $0 }.joined(separator: " · "))
                        .font(.makanBody(12))
                        .foregroundStyle(Color.kicapSecondary)
                        .lineLimit(2)
                }
                Spacer(minLength: 0)
            }

            HStack(spacing: 10) {
                statusPill
                Spacer(minLength: 0)
                if showsTrack {
                    progressTrack
                }
            }

            if let note = submission.reviewNote, !note.isEmpty,
               submission.status == "changes_requested" || submission.status == "rejected" {
                VStack(alignment: .leading, spacing: 4) {
                    Label(Copy.myPlacesReviewerNote, systemImage: "text.bubble.fill")
                        .font(.makanBody(12).weight(.semibold))
                        .foregroundStyle(Color.kicap)
                    Text(note)
                        .font(.makanBody(13))
                        .foregroundStyle(Color.kicap)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .padding(12)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(statusTint.opacity(0.12), in: RoundedRectangle(cornerRadius: 12, style: .continuous))
            }
        }
        .padding(16)
        .background(Color.surface, in: .card)
        .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))
        .opacity(submission.status == "cancelled" ? 0.6 : 1)
        .accessibilityElement(children: .combine)
    }

    private var statusPill: some View {
        Label(statusText, systemImage: statusSymbol)
            .font(.makanBody(12).weight(.semibold))
            .foregroundStyle(Color.kicap)
            .padding(.horizontal, 10)
            .frame(minHeight: 28)
            .background(statusTint.opacity(0.22), in: Capsule())
    }

    /// Sent → Reviewing → Live, for submissions still on the happy path.
    private var progressTrack: some View {
        let reached = submission.status == "approved" ? 3 : 2
        return HStack(spacing: 4) {
            ForEach(0..<3, id: \.self) { step in
                Capsule()
                    .fill(step < reached ? statusTint : Color.hairline)
                    .frame(width: 22, height: 5)
            }
        }
        .accessibilityLabel(reached == 3 ? Copy.myPlacesStepLive : Copy.myPlacesStepReviewing)
    }

    private var showsTrack: Bool {
        submission.status == "pending" || submission.status == "approved"
    }

    private var typeSymbol: String {
        switch submission.submissionType {
        case .newPlace: "mappin.and.ellipse"
        case .editPlace: "pencil"
        case .halalReport: "checkmark.seal"
        case .ownerClaim: "person.badge.key"
        case .closure: "xmark.octagon"
        case .reopen: "arrow.uturn.forward.circle"
        }
    }

    private var typeLabel: String {
        switch submission.submissionType {
        case .newPlace: Copy.myPlacesTypeNew
        case .editPlace: Copy.myPlacesTypeEdit
        case .halalReport: Copy.myPlacesTypeHalal
        case .ownerClaim: Copy.myPlacesTypeOwner
        case .closure: Copy.myPlacesTypeClosure
        case .reopen: Copy.myPlacesTypeReopen
        }
    }

    private var detailLine: String? {
        if submission.submissionType == .halalReport {
            let claim = submission.halalClaim?.pickerLabel
            if let resolved = submission.halalResolvedStatus, resolved != submission.halalClaim {
                return [claim, resolved.pickerLabel].compactMap { $0 }.joined(separator: " → ")
            }
            return claim
        }
        return submission.foodCategory
    }

    private var sentAgo: String? {
        let formatter = ISO8601DateFormatter()
        guard let date = formatter.date(from: submission.createdAt) else { return nil }
        return date.formatted(.relative(presentation: .named))
    }

    private var statusText: String {
        switch submission.status {
        case "pending": Copy.communityStatusPending
        case "approved": Copy.myPlacesStatLive
        case "changes_requested": Copy.communityStatusChangesRequested
        case "rejected": Copy.communityStatusRejected
        case "cancelled": Copy.communityStatusCancelled
        default: submission.status.capitalized
        }
    }

    private var statusSymbol: String {
        switch submission.status {
        case "pending": "clock.fill"
        case "approved": "checkmark.circle.fill"
        case "changes_requested": "exclamationmark.circle.fill"
        case "rejected": "xmark.circle.fill"
        default: "circle"
        }
    }

    private var statusTint: Color {
        switch submission.status {
        case "pending": .kunyit
        case "approved": .pandan
        case "changes_requested": .sambalRed
        default: .kicapSecondary
        }
    }
}
