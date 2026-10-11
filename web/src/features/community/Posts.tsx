import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { communityApi, type Post, type Thread } from '../../api/community'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { Button } from '../../ui/Button'
import { useToast } from '../../ui/Toast'
import { useContributionGate } from '../auth/ContributionGate'
import { PlaceById } from '../nearby/PlaceById'
import { EmptyPosts } from './Community'
import { Composer, length } from './Composer'
import { PostRow } from './PostRow'
import { usePosts } from './usePosts'

/** Calls `onVisible` when the element scrolls into view (infinite lists). */
function useOnVisible(onVisible: () => void) {
  const callback = useRef(onVisible)
  useEffect(() => {
    callback.current = onVisible
  })
  // A callback ref, so it starts watching whenever the sentinel appears: on first render the
  // page is often still loading, and there's no sentinel to watch yet.
  return useCallback((element: HTMLDivElement | null) => {
    if (!element) return
    const observer = new IntersectionObserver((entries) => entries[0]?.isIntersecting && callback.current(), { rootMargin: '200px' })
    observer.observe(element)
    return () => observer.disconnect()
  }, [])
}

function BackToCommunity() {
  return (
    <Link to="/community" className="-ml-1 min-h-11 self-start font-display font-semibold text-sambal-deep">
      ‹ Community
    </Link>
  )
}

/** "See all": every post in the community, newest first, loading more as you scroll. */
export function AllPosts() {
  const { user } = useSession()
  const navigate = useNavigate()
  const posts = usePosts({ limit: 20, enabled: true })
  const { toast, element: toastElement } = useToast()
  const { gate: postGate, run: runPost } = useContributionGate('post in the community')
  const { gate: replyGate, run: runReply } = useContributionGate('reply')
  const [composer, setComposer] = useState<{ parent: Post | null } | null>(null)
  const [placeId, setPlaceId] = useState<number | null>(null)
  const sentinel = useOnVisible(() => posts.hasMore && void posts.loadMore())
  const community = user?.university ?? user?.area ?? 'your community'

  return (
    <div className="flex flex-col gap-5 pt-2 pb-20">
      <BackToCommunity />
      <h1 className="-mt-2 font-display text-3xl font-bold tracking-tight">What {community} is saying</h1>
      {posts.error && !posts.loaded ? (
        <div className="flex flex-col items-start gap-2">
          <p>{errorMessage(posts.error)}</p>
          <Button variant="secondary" className="max-w-xs" onClick={() => void posts.reload()}>
            Retry
          </Button>
        </div>
      ) : posts.loaded && posts.posts.length === 0 ? (
        <EmptyPosts canPost={posts.canPost} reason={posts.cannotPostReason} community={community} onPost={() => runPost(() => setComposer({ parent: null }))} />
      ) : (
        <div className="flex flex-col gap-3">
          {posts.posts.map((post) => (
            <PostRow
              key={post.id}
              post={post}
              variant="card"
              onChange={posts.change}
              onRemove={posts.remove}
              onBlock={posts.removeAuthor}
              onOpenPlace={setPlaceId}
              onReply={(parent) => runReply(() => setComposer({ parent }))}
              onOpenThread={(post) => navigate(`/community/posts/${post.id}`)}
              onToast={toast}
            />
          ))}
          <div ref={sentinel} aria-hidden="true" />
          {posts.loading && <p role="status" className="text-center text-kicap-soft">Loading…</p>}
        </div>
      )}
      {posts.canPost && (
        <button
          type="button"
          onClick={() => runPost(() => setComposer({ parent: null }))}
          className="press fixed bottom-24 right-5 z-20 min-h-12 rounded-full bg-sambal-deep px-5 font-display font-semibold text-white shadow-lift md:bottom-8 md:right-8"
        >
          Share a thought
        </button>
      )}
      <Composer open={composer !== null} parent={composer?.parent ?? null} onClose={() => setComposer(null)} onPosted={posts.added} />
      <PlaceById id={placeId} onClose={() => setPlaceId(null)} />
      {postGate}
      {replyGate}
      {toastElement}
    </div>
  )
}

/** One post and its replies (one level deep), with a reply box. Reply notifications open here. */
export function ThreadPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [thread, setThread] = useState<Thread | null>(null)
  const [missing, setMissing] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const [loadingMore, setLoadingMore] = useState(false)
  const [placeId, setPlaceId] = useState<number | null>(null)
  const { toast, element: toastElement } = useToast()

  useEffect(() => {
    let cancelled = false
    setThread(null)
    setMissing(false)
    setError(null)
    communityApi
      .thread(Number(id))
      .then((loaded) => !cancelled && setThread(loaded))
      .catch((caught) => {
        if (cancelled) return
        if (caught instanceof ApiError && caught.status === 404) setMissing(true)
        else setError(caught)
      })
    return () => {
      cancelled = true
    }
  }, [id])

  const sentinel = useOnVisible(() => {
    if (!thread?.nextCursor || loadingMore) return
    setLoadingMore(true)
    communityApi
      .thread(thread.post.id, thread.nextCursor)
      .then((page) => setThread((current) => (current ? { ...current, replies: [...current.replies, ...page.replies.filter((r) => !current.replies.some((c) => c.id === r.id))], nextCursor: page.nextCursor } : current)))
      .catch(() => setThread((current) => (current ? { ...current, nextCursor: null } : current)))
      .finally(() => setLoadingMore(false))
  })

  if (missing) {
    return (
      <div className="flex flex-col gap-3 pt-2">
        <BackToCommunity />
        <h1 className="font-display text-2xl font-bold">Post unavailable</h1>
        <p className="text-kicap-soft">It may have been deleted or hidden.</p>
      </div>
    )
  }
  if (error) {
    return (
      <div className="flex flex-col items-start gap-3 pt-2">
        <BackToCommunity />
        <p>{errorMessage(error)}</p>
      </div>
    )
  }
  if (!thread) return <p role="status" className="pt-6 text-kicap-soft">Loading…</p>

  const parent = thread.post
  const replyCount = Math.max(parent.replyCount, thread.replies.length)
  const closeThread = () => navigate('/community', { replace: true })

  return (
    <div className="flex flex-col gap-4 pt-2 pb-40 md:pb-28">
      <BackToCommunity />
      <h1 className="sr-only">Thread</h1>
      <PostRow
        post={parent}
        variant="parent"
        onChange={(post) => setThread((current) => current && { ...current, post })}
        onRemove={closeThread}
        onBlock={closeThread}
        onOpenPlace={setPlaceId}
        onToast={toast}
      />
      {thread.replies.length > 0 && <p className="text-xs font-semibold tracking-wide text-kicap-soft">{replyCount === 1 ? '1 reply' : `${replyCount} replies`}</p>}
      <div className="flex flex-col">
        {thread.replies.map((replyPost) => (
          <PostRow
            key={replyPost.id}
            post={replyPost}
            variant="reply"
            onChange={(changed) => setThread((current) => current && { ...current, replies: current.replies.map((r) => (r.id === changed.id ? changed : r)) })}
            onRemove={(removed) => setThread((current) => current && { ...current, replies: current.replies.filter((r) => r.id !== removed.id), post: { ...current.post, replyCount: Math.max(0, current.post.replyCount - 1) } })}
            onBlock={(authorId) => setThread((current) => current && { ...current, replies: current.replies.filter((r) => r.author.id !== authorId) })}
            onOpenPlace={setPlaceId}
            onToast={toast}
          />
        ))}
        <div ref={sentinel} aria-hidden="true" />
      </div>
      {thread.canReply && (
        <ReplyBar
          parent={parent}
          onPosted={(created) =>
            setThread((current) => current && { ...current, replies: [...current.replies, created], post: { ...current.post, replyCount: current.post.replyCount + 1 } })
          }
        />
      )}
      <PlaceById id={placeId} onClose={() => setPlaceId(null)} />
      {toastElement}
    </div>
  )
}

function ReplyBar({ parent, onPosted }: { parent: Post; onPosted: (post: Post) => void }) {
  const { gate, run } = useContributionGate('reply')
  const [text, setText] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const count = length(text)

  function send() {
    run(async () => {
      setBusy(true)
      setError(null)
      try {
        const { post } = await communityApi.create({ body: text.trim(), parentId: parent.id })
        setText('')
        onPosted(post)
      } catch (caught) {
        setError(caught instanceof ApiError ? errorMessage(caught) : 'Couldn’t post that right now. Try again in a bit.')
      } finally {
        setBusy(false)
      }
    })
  }

  return (
    <div className="fixed inset-x-0 bottom-(--tab-bar) z-10 border-t-2 border-hairline bg-surface/95 px-5 py-3 backdrop-blur md:bottom-0 md:left-60 lg:left-64">
      <div className="mx-auto flex max-w-3xl items-end gap-2">
        <label className="flex-1">
          <span className="sr-only">Write a reply</span>
          <textarea value={text} onChange={(event) => setText(event.target.value)} rows={1} placeholder="Write a reply…" className="max-h-32 min-h-11 w-full resize-none rounded-row border-2 border-hairline bg-cream px-3 py-2" />
        </label>
        <button
          type="button"
          aria-label="Send reply"
          onClick={send}
          disabled={count === 0 || count > 280 || busy}
          className="press flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sambal text-white disabled:bg-hairline disabled:text-kicap-soft"
        >
          <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 19V5M5 12l7-7 7 7" />
          </svg>
        </button>
      </div>
      {(count > 250 || error) && (
        <p className={`mx-auto mt-1 max-w-3xl text-sm ${count > 280 || error ? 'text-sambal-deep' : 'text-kicap-soft'}`}>{error ?? `${280 - count} left`}</p>
      )}
      {gate}
    </div>
  )
}
