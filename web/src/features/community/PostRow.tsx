import { useState } from 'react'
import { accountApi, avatarSrc } from '../../api/account'
import { ApiError } from '../../api/client'
import { applyReaction, communityApi, type Post, type Reaction, type ReportReason } from '../../api/community'
import { errorMessage } from '../../lib/errors'
import { categoryLabel } from '../../lib/presentation'
import { relativeTime } from '../../lib/time'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import { useContributionGate } from '../auth/ContributionGate'

/** Community reaction emoji are the one place emoji stay (owner's rule). */
const REACTIONS: [Reaction, string, string][] = [
  ['up', '👍', 'Thumbs up'],
  ['fire', '🔥', 'Fire'],
  ['drool', '🤤', 'Drooling'],
]

export interface PostHandlers {
  onChange: (post: Post) => void
  /** Deleted or reported: gone from this person's view. */
  onRemove: (post: Post) => void
  /** Blocked: every post and reply by this author goes. */
  onBlock: (authorId: number) => void
  onOpenPlace: (id: number) => void
  onReply?: (post: Post) => void
  onOpenThread?: (post: Post) => void
  onToast: (message: string) => void
}

export function AmbassadorBadge({ name }: { name: string }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-full border border-hairline bg-surface px-2 py-0.5 text-xs font-semibold" aria-label={`${name} ambassador`}>
      <span aria-hidden="true" className="text-kunyit">
        ★
      </span>
      <span aria-hidden="true">Ambassador, {name}</span>
    </span>
  )
}

export function PostRow({ post, variant, ...handlers }: { post: Post; variant: 'card' | 'parent' | 'reply' } & PostHandlers) {
  const { gate, run } = useContributionGate('react', { terms: false })
  const [menu, setMenu] = useState(false)
  const [confirm, setConfirm] = useState<'delete' | 'block' | 'report' | null>(null)
  const reply = variant === 'reply'

  function react(type: Reaction) {
    run(() => {
      const before = post
      handlers.onChange(applyReaction(post, type))
      communityApi
        .react(post.id, type)
        .then((result) => handlers.onChange({ ...applyReaction(before, type), ...result }))
        .catch(() => handlers.onChange(before))
    })
  }

  const visibleReplies = post.replies ?? []

  return (
    <article
      className={
        variant === 'card'
          ? 'rounded-card border border-kicap/5 bg-surface p-5 shadow-[0_6px_18px_-12px_rgb(41_32_29/0.25)]'
          : reply
            ? 'border-l-2 border-hairline py-3 pl-4'
            : 'py-2'
      }
    >
      <header className="flex items-start gap-3">
        <img src={avatarSrc(post.author.avatarKey)} alt="" className={`${reply ? 'h-7 w-7' : 'h-10 w-10'} shrink-0 rounded-full bg-kunyit/25 object-cover`} />
        <div className="min-w-0 flex-1">
          <p className="truncate font-semibold">
            {post.author.name}
            {post.isMine && <span className="font-normal text-kicap-soft">, you</span>}
          </p>
          {post.author.ambassadorOf && (
            <div className="mt-0.5">
              <AmbassadorBadge name={post.author.ambassadorOf.name} />
            </div>
          )}
          <p className="text-xs text-kicap-soft">
            <time dateTime={post.createdAt}>{relativeTime(post.createdAt)}</time>
          </p>
        </div>
        <button type="button" aria-label="More options" onClick={() => setMenu(true)} className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xl text-kicap-soft">
          ⋯
        </button>
      </header>

      <button
        type="button"
        disabled={variant !== 'card'}
        onClick={() => handlers.onOpenThread?.(post)}
        className={`mt-2 block w-full whitespace-pre-line text-left ${reply ? 'text-[15px]' : ''} disabled:cursor-text`}
      >
        {post.body}
      </button>

      {post.restaurant && !reply && (
        <button
          type="button"
          onClick={() => handlers.onOpenPlace(post.restaurant!.id)}
          aria-label={`Tagged place: ${post.restaurant.name}`}
          className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-full bg-cream px-3 text-sm font-semibold"
        >
          <svg viewBox="0 0 24 24" className="h-4 w-4 text-sambal" fill="currentColor" aria-hidden="true">
            <path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z" />
          </svg>
          {post.restaurant.name}
          {categoryLabel(post.restaurant.foodCategory) && <span className="font-normal text-kicap-soft">{categoryLabel(post.restaurant.foodCategory)}</span>}
        </button>
      )}

      <footer className="mt-3 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
        {REACTIONS.map(([type, emoji, label]) => {
          const count = post.reactions[type] ?? 0
          const mine = post.myReaction === type
          return (
            <button
              key={type}
              type="button"
              aria-pressed={mine}
              aria-label={`${label}, ${count}`}
              onClick={() => react(type)}
              className={`press inline-flex min-h-10 items-center gap-1 rounded-full border px-3 text-sm ${mine ? 'border-sambal/50 bg-sambal/10 font-semibold text-sambal-deep' : 'border-transparent bg-kicap/5'}`}
            >
              <span aria-hidden="true">{emoji}</span>
              {count > 0 && <span aria-hidden="true">{count}</span>}
            </button>
          )
        })}
        {!reply && handlers.onReply && (
          <button type="button" onClick={() => handlers.onReply!(post)} className="press ml-auto inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-kicap-soft">
            <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinejoin="round" aria-hidden="true">
              <path d="M4 5h16v11H9l-5 4z" />
            </svg>
            {post.replyCount > 0 ? post.replyCount : 'Reply'}
          </button>
        )}
      </footer>

      {variant === 'card' && visibleReplies.length > 0 && (
        <div className="mt-3 flex flex-col gap-1">
          {visibleReplies.map((item) => (
            <PostRow key={item.id} post={item} variant="reply" {...handlers} onChange={(changed) => handlers.onChange({ ...post, replies: visibleReplies.map((r) => (r.id === changed.id ? changed : r)) })} onRemove={() => handlers.onChange({ ...post, replies: visibleReplies.filter((r) => r.id !== item.id), replyCount: Math.max(0, post.replyCount - 1) })} />
          ))}
          {post.replyCount > visibleReplies.length && (
            <button type="button" onClick={() => handlers.onOpenThread?.(post)} className="min-h-11 self-start text-sm font-semibold text-sambal-deep">
              View all {post.replyCount} replies
            </button>
          )}
        </div>
      )}

      <Sheet open={menu} onClose={() => setMenu(false)} title="Post options">
        {post.isMine ? (
          <Button variant="secondary" onClick={() => { setMenu(false); setConfirm('delete') }}>
            Delete
          </Button>
        ) : (
          <>
            <Button variant="secondary" onClick={() => { setMenu(false); setConfirm('report') }}>
              Report
            </Button>
            {post.author.id !== null && (
              <Button variant="secondary" onClick={() => { setMenu(false); setConfirm('block') }}>
                Block {post.author.name}
              </Button>
            )}
          </>
        )}
        <Button variant="quiet" onClick={() => setMenu(false)}>
          Cancel
        </Button>
      </Sheet>

      <Sheet open={confirm === 'delete'} onClose={() => setConfirm(null)} title="Delete this post?">
        <Button
          onClick={() => {
            setConfirm(null)
            void communityApi.remove(post.id).then(() => handlers.onRemove(post)).catch((caught) => handlers.onToast(errorMessage(caught)))
          }}
        >
          Delete
        </Button>
        <Button variant="quiet" onClick={() => setConfirm(null)}>
          Cancel
        </Button>
      </Sheet>

      <Sheet open={confirm === 'block'} onClose={() => setConfirm(null)} title={`Block ${post.author.name}?`}>
        <p>You won’t see each other’s posts or replies. You can unblock in Settings, Blocked people.</p>
        <Button
          onClick={() => {
            setConfirm(null)
            const authorId = post.author.id!
            void accountApi.block(authorId).then(() => handlers.onBlock(authorId)).catch((caught) => handlers.onToast(errorMessage(caught)))
          }}
        >
          Block
        </Button>
        <Button variant="quiet" onClick={() => setConfirm(null)}>
          Cancel
        </Button>
      </Sheet>

      <ReportSheet
        open={confirm === 'report'}
        onClose={() => setConfirm(null)}
        onReported={() => {
          setConfirm(null)
          handlers.onRemove(post)
          handlers.onToast('Thanks, we’ll take a look. You won’t see this post again.')
        }}
        postId={post.id}
      />
      {gate}
    </article>
  )
}

const REASONS: [ReportReason, string][] = [
  ['spam', 'Spam or advertising'],
  ['offensive', 'Offensive or hateful'],
  ['harassment', 'Harassment or bullying'],
  ['misleading', 'False or misleading'],
  ['other', 'Something else'],
]

function ReportSheet({ open, onClose, onReported, postId }: { open: boolean; onClose: () => void; onReported: () => void; postId: number }) {
  const [reason, setReason] = useState<ReportReason | null>(null)
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function send() {
    if (!reason) return
    setBusy(true)
    setError(null)
    try {
      await communityApi.report(postId, reason, note.trim() || null)
      onReported()
    } catch (caught) {
      setError(caught instanceof ApiError ? errorMessage(caught) : 'Couldn’t post that right now. Try again in a bit.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Sheet open={open} onClose={onClose} title="Report post">
      <p className="text-kicap-soft">Reports are anonymous. We review every one, and posts with several reports are hidden automatically.</p>
      <fieldset>
        <legend className="font-display font-semibold">Why are you reporting this?</legend>
        {REASONS.map(([value, label]) => (
          <label key={value} className="flex min-h-11 cursor-pointer items-center gap-3">
            <input type="radio" name="reason" checked={reason === value} onChange={() => setReason(value)} className="h-5 w-5 accent-[var(--color-sambal)]" />
            {label}
          </label>
        ))}
      </fieldset>
      <label className="flex flex-col gap-1">
        <span className="text-sm font-semibold">Anything else we should know? (optional)</span>
        <textarea value={note} onChange={(event) => setNote(event.target.value.slice(0, 500))} rows={3} className="rounded-row border-2 border-hairline bg-cream p-3" />
      </label>
      {error && <p role="alert" className="text-sambal-deep">{error}</p>}
      <Button onClick={() => void send()} disabled={!reason} busy={busy}>
        Send report
      </Button>
      <Button variant="quiet" onClick={onClose}>
        Cancel
      </Button>
    </Sheet>
  )
}
