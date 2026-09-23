import SwiftUI

/// Everything a post row can ask its screen to do. Rows stay presentation-only; the screen's
/// `CommunityPostInteractions` decides what each tap means (sheet, dialog, push).
@MainActor
@Observable
final class CommunityPostInteractions {
    var composerParent: CommunityPost?
    var isComposingNew = false
    var reportTarget: CommunityPost?
    var blockTarget: CommunityPost?
    var deleteTarget: CommunityPost?
    var threadTarget: CommunityPost?
    var placeTarget: CommunityPostPlace?
    var toast: String?
}

struct CommunityPostRow: View {
    enum Style {
        /// Top-level card in a feed, with its reply preview.
        case card
        /// The pinned parent at the top of a thread — no preview, no "view all".
        case threadParent
        /// A compact reply, inside a card preview or a thread.
        case reply
    }

    let post: CommunityPost
    var style: Style = .card
    let onReact: (CommunityPostRow.ReactionTap) -> Void

    struct ReactionTap {
        let post: CommunityPost
        let type: CommunityReactionType
    }

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(CommunityPostInteractions.self) private var interactions

    var body: some View {
        VStack(alignment: .leading, spacing: style == .reply ? 8 : 16) {
            header
            Text(post.body)
                .font(.system(style == .reply ? .subheadline : .body, design: .rounded))
                .lineSpacing(3)
                .foregroundStyle(Color.kicap)
                .fixedSize(horizontal: false, vertical: true)
                .textSelection(.enabled)

            if let place = post.restaurant, style != .reply {
                placePill(place)
            }

            if style != .reply {
                Rectangle()
                    .fill(Color.kicap.opacity(0.06))
                    .frame(height: 1)
                    .accessibilityHidden(true)
            }

            footer

            if style == .card, let replies = post.replies, !replies.isEmpty {
                replyPreview(replies)
            }
        }
        .padding(style == .reply ? 0 : 20)
        .background(style == .reply ? Color.clear : Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 24))
        .overlay {
            if style != .reply {
                RoundedRectangle(cornerRadius: 24)
                    .strokeBorder(Color.kicap.opacity(0.06))
            }
        }
        .shadow(color: Color.kicap.opacity(style == .reply ? 0 : 0.04), radius: 16, y: 6)
        .contentShape(Rectangle())
        .onTapGesture {
            if style == .card { interactions.threadTarget = post }
        }
    }

    // MARK: - Pieces

    private var header: some View {
        HStack(spacing: 8) {
            Image(AvatarCharacter(key: post.author.avatarKey).imageName)
                .resizable()
                .scaledToFill()
                .frame(width: avatarSize, height: avatarSize)
                .background(Color.kunyit.opacity(0.25))
                .clipShape(Circle())
                .accessibilityHidden(true)

            VStack(alignment: .leading, spacing: 0) {
                HStack(spacing: 4) {
                    Text(post.author.name)
                        .font(.system(.subheadline, design: .rounded, weight: .semibold))
                        .foregroundStyle(Color.kicap)
                        .lineLimit(1)
                    if post.isMine {
                        Text("· You")
                            .font(.makanBody(12))
                            .foregroundStyle(.secondary)
                    }
                }
                if let date = post.createdDate {
                    Text(date, format: .relative(presentation: .named, unitsStyle: .abbreviated))
                        .font(.makanBody(12))
                        .foregroundStyle(.secondary)
                }
            }

            Spacer(minLength: 0)
            moreMenu
        }
    }

    private var avatarSize: CGFloat { style == .reply ? 28 : 40 }

    private var moreMenu: some View {
        Menu {
            if post.isMine {
                Button(role: .destructive) {
                    interactions.deleteTarget = post
                } label: {
                    Label("Delete", systemImage: "trash")
                }
            } else {
                Button {
                    interactions.reportTarget = post
                } label: {
                    Label("Report", systemImage: "flag")
                }
                if post.author.id != nil {
                    Button(role: .destructive) {
                        interactions.blockTarget = post
                    } label: {
                        Label("Block \(post.author.name)", systemImage: "hand.raised")
                    }
                }
            }
        } label: {
            Image(systemName: "ellipsis")
                .font(.system(size: 15, weight: .semibold))
                .foregroundStyle(.secondary)
                .frame(width: 44, height: 44)
                .contentShape(Rectangle())
        }
        .accessibilityLabel("More options")
        .padding(.trailing, -12)
        .padding(.vertical, -10)
    }

    private func placePill(_ place: CommunityPostPlace) -> some View {
        Button {
            interactions.placeTarget = place
        } label: {
            HStack(spacing: 6) {
                Image(systemName: "mappin.circle.fill")
                    .foregroundStyle(Color.sambalRed)
                Text(place.name)
                    .foregroundStyle(Color.kicap)
                    .lineLimit(1)
                if let category = place.foodCategory, !category.isEmpty {
                    Text("· \(category)")
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }
                Image(systemName: "chevron.right")
                    .font(.system(size: 10, weight: .semibold))
                    .foregroundStyle(.secondary)
            }
            .font(.makanBody(13))
            .padding(.horizontal, 10)
            .frame(minHeight: 44)
            .background(Color.nasiCream)
            .clipShape(Capsule())
        }
        .buttonStyle(CommunityPressStyle())
        .accessibilityLabel("Tagged place: \(place.name)")
    }

    private var footer: some View {
        HStack(spacing: 6) {
            ForEach(CommunityReactionType.allCases) { type in
                reactionButton(type)
            }

            Spacer(minLength: 0)

            if style != .reply {
                Button {
                    interactions.composerParent = post
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "bubble.left")
                        Text(post.replyCount > 0 ? "\(post.replyCount)" : Copy.communityPostsReply)
                    }
                    .font(.makanBody(13))
                    .foregroundStyle(.secondary)
                    .frame(minHeight: 44)
                    .padding(.horizontal, 6)
                    .contentShape(Rectangle())
                }
                .buttonStyle(CommunityPressStyle())
                .accessibilityLabel(post.replyCount > 0 ? "\(post.replyCount) replies. Reply" : "Reply")
            }
        }
    }

    private func reactionButton(_ type: CommunityReactionType) -> some View {
        let count = post.count(for: type)
        let isSelected = post.myReaction == type

        return Button {
            UIImpactFeedbackGenerator(style: .light).impactOccurred()
            onReact(ReactionTap(post: post, type: type))
        } label: {
            HStack(spacing: 3) {
                Text(type.emoji)
                if count > 0 {
                    Text("\(count)")
                        .contentTransition(.numericText())
                        .foregroundStyle(isSelected ? Color.sambalRed : .secondary)
                }
            }
            .font(.makanBody(style == .reply ? 12 : 13))
            .padding(.horizontal, 9)
            .frame(minHeight: 44)
            .background(isSelected ? Color.sambalRed.opacity(0.12) : Color.kicap.opacity(0.05))
            .overlay(Capsule().stroke(isSelected ? Color.sambalRed.opacity(0.5) : .clear, lineWidth: 1))
            .clipShape(Capsule())
            .contentShape(Capsule())
        }
        .buttonStyle(CommunityPressStyle())
        .animation(reduceMotion ? nil : Motion.standard, value: count)
        .animation(reduceMotion ? nil : Motion.quick, value: isSelected)
        .accessibilityLabel("\(type.accessibilityName), \(count)")
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }

    private func replyPreview(_ replies: [CommunityPost]) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            ForEach(replies) { reply in
                CommunityPostRow(post: reply, style: .reply, onReact: onReact)
            }
            if post.replyCount > replies.count {
                Button {
                    interactions.threadTarget = post
                } label: {
                    Text(String(format: Copy.communityPostsViewAllRepliesFormat, post.replyCount))
                        .font(.makanBody(13))
                        .foregroundStyle(Color.sambalRed)
                        .frame(minHeight: 32)
                }
                .buttonStyle(CommunityPressStyle())
            }
        }
        .padding(.leading, 12)
        .padding(.top, 2)
        .overlay(alignment: .leading) {
            Rectangle()
                .fill(Color.kicap.opacity(0.1))
                .frame(width: 2)
        }
    }
}
