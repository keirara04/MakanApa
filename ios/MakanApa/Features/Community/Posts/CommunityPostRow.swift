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

    @Environment(CommunityPostInteractions.self) private var interactions

    var body: some View {
        VStack(alignment: .leading, spacing: style == .reply ? 6 : 10) {
            header
            Text(post.body)
                .font(.makanBody(style == .reply ? 14 : 15))
                .foregroundStyle(Color.kicap)
                .fixedSize(horizontal: false, vertical: true)
                .textSelection(.enabled)

            if let place = post.restaurant, style != .reply {
                placePill(place)
            }

            footer

            if style == .card, let replies = post.replies, !replies.isEmpty {
                replyPreview(replies)
            }
        }
        .padding(style == .reply ? 0 : 14)
        .background(style == .reply ? Color.clear : Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 18))
        .shadow(color: .black.opacity(style == .reply ? 0 : 0.05), radius: 6, y: 2)
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
                        .font(.makanBody(style == .reply ? 13 : 14))
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

    private var avatarSize: CGFloat { style == .reply ? 26 : 34 }

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
            .padding(.vertical, 7)
            .background(Color.nasiCream)
            .clipShape(Capsule())
        }
        .buttonStyle(.plain)
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
                    .frame(minHeight: 36)
                    .padding(.horizontal, 6)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
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
            .frame(minHeight: style == .reply ? 28 : 32)
            .background(isSelected ? Color.sambalRed.opacity(0.12) : Color.kicap.opacity(0.05))
            .overlay(Capsule().stroke(isSelected ? Color.sambalRed.opacity(0.5) : .clear, lineWidth: 1))
            .clipShape(Capsule())
            .contentShape(Capsule())
        }
        .buttonStyle(.plain)
        .animation(.easeOut(duration: 0.15), value: count)
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
                .buttonStyle(.plain)
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
