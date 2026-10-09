import SwiftUI

/// "Become an ambassador" for the member's own community. Checks the latest application first and
/// shows where it stands (sent, approved, not approved) instead of the form, so the server's
/// "already applied" refusals are only ever a race fallback.
struct AmbassadorApplicationSheet: View {
    /// The community the member belongs to — what they'd represent.
    let communityName: String

    @Environment(\.dismiss) private var dismiss
    @State private var application: AmbassadorApplication?
    @State private var hasLoaded = false
    @State private var loadFailed = false
    @State private var reason = ""
    @State private var instagram = ""
    @State private var isSending = false
    @State private var sendError: String?
    @State private var sentCount = 0
    @FocusState private var focusedField: Field?

    private enum Field { case reason, instagram }

    private static let minimumReason = 20
    private static let maximumReason = 500

    var body: some View {
        AccountRequired(feature: "apply to be an ambassador") {
            NavigationStack {
                content
                    .background(Color.nasiCream.ignoresSafeArea())
                    .navigationTitle(Copy.ambassadorApplyTitle)
                    .navigationBarTitleDisplayMode(.inline)
                    .toolbar {
                        ToolbarItem(placement: .cancellationAction) {
                            Button(Copy.close) { dismiss() }
                        }
                    }
            }
            .task { await load() }
            .sensoryFeedback(.success, trigger: sentCount)
            .interactiveDismissDisabled(isSending || (!reason.isEmpty && application?.status != "pending"))
        }
    }

    @ViewBuilder
    private var content: some View {
        if !hasLoaded {
            ProgressView()
                .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else if loadFailed {
            VStack(spacing: 12) {
                Label(Copy.ambassadorApplyLoadFailed, systemImage: "exclamationmark.circle.fill")
                    .font(.makanBody(14))
                    .foregroundStyle(Color.sambalRed)
                Button(Copy.tryAgain) { Task { await load() } }
                    .font(.makanBody(15).weight(.semibold))
                    .foregroundStyle(Color.sambalRed)
                    .frame(minHeight: 44)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else if let application, application.status == "pending" {
            statusView(
                symbol: "paperplane.fill", tint: .kunyit,
                title: Copy.ambassadorApplySentTitle, detail: Copy.ambassadorApplySentDetail
            )
        } else if let application, application.status == "approved" {
            statusView(
                symbol: "star.fill", tint: .pandan,
                title: String(format: Copy.ambassadorApplyApprovedTitleFormat, application.communityName ?? communityName),
                detail: Copy.ambassadorApplyApprovedDetail
            )
        } else {
            form
        }
    }

    // MARK: - Form

    private var form: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 22) {
                VStack(spacing: 12) {
                    MascotView(mood: .celebrate, size: 96)
                    Text(String(format: Copy.ambassadorApplyHeadlineFormat, communityName))
                        .font(.makanDisplay(22))
                        .foregroundStyle(Color.kicap)
                        .multilineTextAlignment(.center)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .frame(maxWidth: .infinity)

                VStack(alignment: .leading, spacing: 12) {
                    perk("star.fill", String(format: Copy.ambassadorApplyPerkPicksFormat, communityName))
                    perk("person.3.fill", String(format: Copy.ambassadorApplyPerkSeenFormat, communityName))
                    perk("person.text.rectangle.fill", Copy.ambassadorApplyPerkCard)
                }
                .padding(16)
                .background(Color.surface, in: .card)
                .overlay(RoundedRectangle.card.strokeBorder(Color.hairline, lineWidth: 1))

                if let application, application.status == "declined" {
                    declinedNote(application)
                }

                VStack(alignment: .leading, spacing: 6) {
                    Text(Copy.ambassadorApplyReasonLabel)
                        .font(.makanBody(13).weight(.semibold))
                        .foregroundStyle(Color.kicap)
                    TextField(Copy.ambassadorApplyReasonPlaceholder, text: $reason, axis: .vertical)
                        .lineLimit(4...8)
                        .focused($focusedField, equals: .reason)
                        .fieldBox(isFocused: focusedField == .reason)
                        .onChange(of: reason) { _, new in
                            if new.count > Self.maximumReason { reason = String(new.prefix(Self.maximumReason)) }
                        }
                    HStack {
                        Text(Copy.ambassadorApplyReasonFooter)
                        Spacer()
                        Text(String(format: Copy.ambassadorApplyCountFormat, reason.count))
                            .monospacedDigit()
                    }
                    .font(.makanBody(12))
                    .foregroundStyle(Color.kicapSecondary)
                }

                VStack(alignment: .leading, spacing: 6) {
                    Text(Copy.ambassadorApplyInstagramLabel)
                        .font(.makanBody(13).weight(.semibold))
                        .foregroundStyle(Color.kicap)
                    TextField(Copy.ambassadorApplyInstagramPlaceholder, text: $instagram)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .focused($focusedField, equals: .instagram)
                        .fieldBox(isFocused: focusedField == .instagram)
                }

                if let sendError {
                    Label(sendError, systemImage: "exclamationmark.circle.fill")
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                }
            }
            .padding(.horizontal, 20)
            .padding(.top, 12)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .safeAreaInset(edge: .bottom) {
            Button {
                Task { await send() }
            } label: {
                ZStack {
                    Text(Copy.ambassadorApplySend).opacity(isSending ? 0 : 1)
                    if isSending { ProgressView().tint(.white) }
                }
                .font(.makanBody(16).weight(.semibold))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity, minHeight: 52)
                .background(canSend ? Color.sambalRed : Color.sambalRed.opacity(0.35), in: Capsule())
            }
            .buttonStyle(PressCompressStyle())
            .disabled(!canSend || isSending)
            .padding(.horizontal, 20)
            .padding(.vertical, 10)
            .background(Color.nasiCream)
        }
    }

    private func perk(_ symbol: String, _ text: String) -> some View {
        Label {
            Text(text)
                .font(.makanBody(14))
                .foregroundStyle(Color.kicap)
                .fixedSize(horizontal: false, vertical: true)
        } icon: {
            Image(systemName: symbol)
                .foregroundStyle(Color.sambalRed)
        }
    }

    private func declinedNote(_ application: AmbassadorApplication) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Label(Copy.ambassadorApplyDeclinedTitle, systemImage: "info.circle.fill")
                .font(.makanBody(14).weight(.semibold))
                .foregroundStyle(Color.kicap)
            Text(application.reviewNote ?? Copy.ambassadorApplyDeclinedDetail)
                .font(.makanBody(13))
                .foregroundStyle(Color.kicap)
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.kunyit.opacity(0.18), in: .row)
    }

    private func statusView(symbol: String, tint: Color, title: String, detail: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: symbol)
                .font(.system(size: 30, weight: .semibold))
                .foregroundStyle(Color.kicap)
                .frame(width: 76, height: 76)
                .background(tint.opacity(0.3), in: Circle())
                .accessibilityHidden(true)
            Text(title)
                .font(.makanDisplay(22))
                .foregroundStyle(Color.kicap)
                .multilineTextAlignment(.center)
            Text(detail)
                .font(.makanBody(15))
                .foregroundStyle(Color.kicapSecondary)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)
            Button(Copy.guideDone) { dismiss() }
                .font(.makanBody(16).weight(.semibold))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity, minHeight: 52)
                .background(Color.sambalRed, in: Capsule())
                .buttonStyle(PressCompressStyle())
                .padding(.top, 8)
        }
        .padding(.horizontal, 28)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .transition(.opacity.combined(with: .scale(scale: 0.95)))
    }

    // MARK: - Data

    private var trimmedReason: String { reason.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canSend: Bool { trimmedReason.count >= Self.minimumReason }

    @MainActor
    private func load() async {
        loadFailed = false
        do {
            application = try await APIClient.myAmbassadorApplication().application
            hasLoaded = true
            // Approved since the session last loaded — pull the new role so the tools show up now.
            if application?.status == "approved", AmbassadorPickStore.currentRole == nil {
                await AuthStore.shared.refreshUser()
            }
        } catch APIError.unauthorized {
            AuthStore.shared.handleUnauthorized()
        } catch {
            loadFailed = true
            hasLoaded = true
        }
    }

    @MainActor
    private func send() async {
        guard canSend else { return }
        focusedField = nil
        isSending = true
        sendError = nil
        defer { isSending = false }
        let handle = instagram.trimmingCharacters(in: .whitespaces)
        do {
            let response = try await APIClient.applyForAmbassador(reason: trimmedReason, instagramHandle: handle.isEmpty ? nil : handle)
            withAnimation(Motion.standard) { application = response.application }
            sentCount += 1
        } catch let error as APIError {
            if case .unauthorized = error { AuthStore.shared.handleUnauthorized(); return }
            sendError = error.serverMessage ?? Copy.ambassadorApplySendFailed
        } catch {
            sendError = Copy.ambassadorApplySendFailed
        }
    }
}

private extension View {
    /// The white rounded field look used across the app's forms.
    func fieldBox(isFocused: Bool) -> some View {
        self
            .font(.makanBody(16))
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .background(Color.surface, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .strokeBorder(isFocused ? Color.sambalRed.opacity(0.6) : Color.hairline, lineWidth: isFocused ? 1.5 : 1)
            )
    }
}
