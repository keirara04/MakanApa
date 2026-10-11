import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Link, useNavigate } from 'react-router'
import { communityApi, type CommunityTag, type Post } from '../../api/community'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { categoryLabel, priceLabel } from '../../lib/presentation'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import { useToast } from '../../ui/Toast'
import { useContributionGate } from '../auth/ContributionGate'
import { PlaceById } from '../nearby/PlaceById'
import { CommunityPicker } from '../settings/EditProfile'
import { Chevron } from '../settings/Settings'
import { AmbassadorRail, ApplySheet } from './Ambassador'
import { Composer } from './Composer'
import { PostRow } from './PostRow'
import { usePosts } from './usePosts'

export const VIBE_LABELS: Record<CommunityTag, string> = {
  chill: 'Chill', study: 'Study', student_budget: 'Student budget', hidden_gem: 'Hidden gem', date: 'Date', lepak: 'Hangout', family: 'Family', late_night: 'Late night',
}

function titleCase(value: string): string {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

/** "Malay", "Hamburger": the place's kind, from its category or else its cuisines. */
function kind(item: { foodCategory: string | null; cuisines: string[] }): string {
  return categoryLabel(item.foodCategory) ?? item.cuisines.map(titleCase).join(', ')
}

/** Community: what your university or area (or everyone nearby) is eating and saying. */
export function Community() {
  const { user } = useSession()
  const navigate = useNavigate()
  const location = useLocation()
  const queryClient = useQueryClient()
  const coords = location.state.status === 'ready' ? location.state.coords : null
  const affiliated = user?.affiliationType === 'university' || user?.affiliationType === 'area'
  const community = user?.university ?? user?.area ?? null
  const communityLabel = community ?? 'your community'
  const needsLocation = !affiliated && !coords

  const feed = useQuery({
    queryKey: ['community-feed', user?.affiliationType, community, coords?.latitude.toFixed(2), coords?.longitude.toFixed(2)],
    queryFn: () => communityApi.feed(coords),
    enabled: !needsLocation,
  })
  const posts = usePosts({ limit: 3, enabled: affiliated })
  const { toast, element: toastElement } = useToast()
  const { gate: postGate, run: runPost } = useContributionGate('post in the community')
  const { gate: replyGate, run: runReply } = useContributionGate('reply')
  const { gate: applyGate, run: runApply } = useContributionGate('apply to be an ambassador')
  const [composer, setComposer] = useState<{ parent: Post | null } | null>(null)
  const [placeId, setPlaceId] = useState<number | null>(null)
  const [choosing, setChoosing] = useState(false)
  const [plusMenu, setPlusMenu] = useState(false)
  const [applying, setApplying] = useState(false)
  const [guide, setGuide] = useState(false)

  const handlers = {
    onChange: posts.change,
    onRemove: posts.remove,
    onBlock: posts.removeAuthor,
    onOpenPlace: setPlaceId,
    onReply: (post: Post) => runReply(() => setComposer({ parent: post })),
    onOpenThread: (post: Post) => navigate(`/community/posts/${post.id}`),
    onToast: toast,
  }
  const refreshFeed = () => void queryClient.invalidateQueries({ queryKey: ['community-feed'] })

  return (
    <div className="flex flex-col gap-8 pt-2">
      <header className="flex flex-col gap-4">
        <div className="flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={() => setChoosing(true)}
            aria-label={`Community, ${community ?? 'Public'}. Change community`}
            className="press inline-flex min-h-11 items-center gap-2 rounded-full border border-hairline bg-surface pl-4 pr-3 font-display font-semibold shadow-sm"
          >
            {community ?? (user?.affiliationType ? 'Public' : 'Not assigned')}
            <svg viewBox="0 0 24 24" className="h-4 w-4 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="m6 9 6 6 6-6" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => setPlusMenu(true)}
            aria-label="Add a place or see your places"
            className="press inline-flex min-h-11 items-center gap-1.5 rounded-full bg-sambal pl-3 pr-4 font-display font-semibold text-white shadow-lift"
          >
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" aria-hidden="true">
              <path d="M12 5v14M5 12h14" />
            </svg>
            Add
          </button>
        </div>
        <div>
          <h1 className="font-display text-4xl font-bold tracking-tight md:text-5xl">{affiliated ? `What’s ${community} eating?` : 'What’s trending near you'}</h1>
          <p className="mt-1 text-kicap-soft">{affiliated ? `Popular around ${community}` : 'Trending picks nearby'}</p>
        </div>
      </header>

      {needsLocation ? (
        <section className="flex flex-col items-start gap-3 rounded-card bg-surface p-6">
          {location.state.status === 'denied' ? (
            <>
              <h2 className="font-display text-xl font-bold">Location is off</h2>
              <p className="text-kicap-soft">Turn on location for this site (the icon next to the address bar) to see what’s trending nearby. Or pick your university or area above.</p>
            </>
          ) : (
            <>
              <h2 className="font-display text-xl font-bold">See what’s trending around you</h2>
              <p className="text-kicap-soft">MakanApa uses your location to find popular picks nearby.</p>
              <Button className="max-w-xs" onClick={() => void location.request()}>
                Continue
              </Button>
            </>
          )}
        </section>
      ) : feed.isLoading ? (
        <div className="flex flex-col gap-3" aria-label="Loading">
          {[0, 1, 2, 3, 4].map((index) => (
            <div key={index} className="h-20 animate-pulse rounded-[18px] bg-hairline/60" />
          ))}
        </div>
      ) : feed.isError ? (
        <section className="flex flex-col items-center gap-3 py-8 text-center">
          <p>{errorMessage(feed.error)}</p>
          <Button variant="secondary" className="max-w-xs" onClick={() => void feed.refetch()}>
            Retry
          </Button>
        </section>
      ) : (
        feed.data && (
          <>
            {affiliated && <AmbassadorRail picks={feed.data.ambassadorPicks} community={communityLabel} onOpenPlace={setPlaceId} onChanged={refreshFeed} />}

            {feed.data.newInArea.length > 0 && (
              <Section title="New in your area">
                <ul className="-mx-5 flex gap-3 overflow-x-auto px-5 pb-2 [scrollbar-width:none] md:mx-0 md:px-0">
                  {feed.data.newInArea.map((item) => (
                    <li key={item.id} className="w-40 shrink-0">
                      <button type="button" onClick={() => setPlaceId(item.id)} className="press flex h-full w-full flex-col rounded-row border border-hairline bg-surface p-3 text-left shadow-sm" aria-label={`${item.name}, newly added${item.rating ? `, rated ${item.rating.toFixed(1)}` : ''}`}>
                        <span className="text-xs font-semibold text-pandan">New</span>
                        <span className="truncate font-semibold">{item.name}</span>
                        <span className="truncate text-xs text-kicap-soft">{kind(item)}</span>
                        <span className="mt-auto pt-2 text-xs">
                          {[item.rating !== null ? `★ ${item.rating.toFixed(1)}` : null, item.distanceKm !== null ? `${item.distanceKm.toFixed(1)} km` : null].filter(Boolean).join(', ')}
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
              </Section>
            )}

            {affiliated && (
              <Section
                title={`What ${community} is saying`}
                action={
                  posts.posts.length > 0 || posts.hasMore ? (
                    <Link to="/community/posts" className="font-semibold text-sambal-deep">
                      See all
                    </Link>
                  ) : null
                }
              >
                {!posts.loaded ? (
                  <div className="h-28 animate-pulse rounded-card bg-hairline/60" />
                ) : posts.posts.length === 0 && posts.hasMore ? (
                  // The few shown were removed or blocked; there are more.
                  <Link to="/community/posts" className="rounded-row border-2 border-hairline px-4 py-3 font-semibold text-sambal-deep">
                    See what else people are saying
                  </Link>
                ) : posts.posts.length === 0 ? (
                  <EmptyPosts canPost={posts.canPost} reason={posts.cannotPostReason} community={communityLabel} onPost={() => runPost(() => setComposer({ parent: null }))} />
                ) : (
                  <div className="flex flex-col gap-3">
                    {posts.posts.slice(0, 3).map((post) => (
                      <PostRow key={post.id} post={post} variant="card" {...handlers} />
                    ))}
                    {posts.canPost && <Button onClick={() => runPost(() => setComposer({ parent: null }))}>Share a thought</Button>}
                  </div>
                )}
              </Section>
            )}

            {feed.data.trending.length > 0 && (
              <Section title="Trending around you">
                <ol className="divide-y divide-hairline overflow-hidden rounded-card border border-hairline bg-surface shadow-sm">
                  {feed.data.trending.map((item, index) => (
                    <li key={item.id}>
                      <button type="button" onClick={() => setPlaceId(item.id)} className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-cream/60 active:bg-cream motion-reduce:transition-none">
                        <span
                          className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-display font-bold ${index < 3 ? 'bg-sambal text-lg text-white' : 'bg-kicap/8 text-kicap-soft'}`}
                        >
                          {index + 1}
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate font-display text-base font-semibold">{item.name}</span>
                          <span className="block truncate text-sm text-kicap-soft">
                            {[kind(item), item.rating !== null ? `★ ${item.rating.toFixed(1)}` : null, item.distanceKm !== null ? `${item.distanceKm.toFixed(1)} km` : null, priceLabel(item.priceLevel)]
                              .filter(Boolean)
                              .join(', ')}
                          </span>
                          <span className="mt-0.5 flex flex-wrap items-center gap-2 text-sm font-semibold text-sambal-deep">
                            {item.pickerCount} {item.pickerCount === 1 ? 'person' : 'people'} picked this
                            {item.trendingVibe && <span className="rounded-full bg-kunyit/25 px-2 py-0.5 text-xs text-kicap">{VIBE_LABELS[item.trendingVibe] ?? titleCase(item.trendingVibe)}</span>}
                          </span>
                        </span>
                        <Chevron />
                      </button>
                    </li>
                  ))}
                </ol>
              </Section>
            )}

            {feed.data.trending.length === 0 && feed.data.newInArea.length === 0 && (
              <ActionCard icon="add" title="Know a spot worth sharing?" detail="Add it so your community can find it." onClick={() => navigate('/community/add')} />
            )}
          </>
        )
      )}

      {affiliated && !user?.ambassadorOf && (
        <ActionCard icon="star" title={`Become an ambassador for ${communityLabel}`} detail="Pick the best spots for your community." onClick={() => runApply(() => setApplying(true))} />
      )}
      <ActionCard icon="help" title="New to Community?" detail="See how to add places, post and react." onClick={() => setGuide(true)} />

      <Composer
        open={composer !== null}
        parent={composer?.parent ?? null}
        onClose={() => setComposer(null)}
        onPosted={(post) => {
          posts.added(post)
          toast(post.parentId ? 'Reply posted' : 'Posted')
        }}
      />
      <PlaceById id={placeId} onClose={() => setPlaceId(null)} />
      <CommunityPicker
        open={choosing}
        onClose={() => {
          setChoosing(false)
          refreshFeed()
          void posts.reload()
        }}
      />
      <ApplySheet open={applying} onClose={() => setApplying(false)} community={communityLabel} />
      <Sheet open={plusMenu} onClose={() => setPlusMenu(false)} title="Places">
        <Button onClick={() => navigate('/community/add')}>Add a place</Button>
        <Button variant="secondary" onClick={() => navigate('/community/mine')}>
          My places
        </Button>
        <Button variant="quiet" onClick={() => setPlusMenu(false)}>
          Cancel
        </Button>
      </Sheet>
      <CommunityGuide open={guide} onClose={() => setGuide(false)} />
      {postGate}
      {replyGate}
      {applyGate}
      {toastElement}
    </div>
  )
}

function Section({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section>
      <div className="mb-3 flex items-baseline justify-between gap-3">
        <h2 className="font-display text-xl font-bold">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

const ACTION_ICONS = {
  add: <path d="M12 5v14M5 12h14" />,
  star: <path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z" />,
  help: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M9.7 9.5a2.4 2.4 0 1 1 3.4 2.2c-.7.3-1.1.9-1.1 1.6v.4M12 16.6v.1" />
    </>
  ),
}

function ActionCard({ icon, title, detail, onClick }: { icon: keyof typeof ACTION_ICONS; title: string; detail: string; onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="press flex min-h-14 items-center gap-3 rounded-card border border-hairline bg-surface px-4 py-3 text-left shadow-sm">
      <span aria-hidden="true" className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-kunyit/30 text-kicap">
        <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          {ACTION_ICONS[icon]}
        </svg>
      </span>
      <span className="flex-1">
        <span className="block font-display font-semibold">{title}</span>
        <span className="text-sm text-kicap-soft">{detail}</span>
      </span>
      <Chevron />
    </button>
  )
}

export function EmptyPosts({ canPost, reason, community, onPost }: { canPost: boolean; reason: string | null; community: string; onPost: () => void }) {
  return (
    <div className="flex flex-col items-center gap-2 rounded-card border-2 border-dashed border-hairline px-6 py-8 text-center">
      <p className="font-display text-xl font-bold">No one’s said anything yet</p>
      <p className="text-kicap-soft">{canPost ? `Be the first to share what ${community} is eating` : (reason ?? 'Join a university or area community to see and share thoughts.')}</p>
      {canPost && (
        <Button className="mt-2 max-w-xs" onClick={onPost}>
          Share a thought
        </Button>
      )}
    </div>
  )
}

const GUIDE = [
  ['Pick your community', 'Tap the name at the top to switch between your university, your area, or everyone nearby.'],
  ['Add a place', 'Tap + and choose Add a place. Search first: if it’s already listed you can suggest an edit, and if it’s on Google we fill in the details for you.'],
  ['Track what you added', 'Tap + and choose My places to see what’s in review and what’s live. We’ll notify you once it’s checked.'],
  ['Share a thought', 'Tap Share a thought under the posts. Tag a place so others can open it straight from your post.'],
  ['React and reply', 'Tap a reaction under any post, or Reply to answer it.'],
  ['Open any place', 'Tap a place for directions, photos and the menu. You can add photos or menu items, or help confirm if it’s halal.'],
  ['Keep it friendly', 'Tap the three dots on a post to report it or block someone. Our team reviews every report.'],
] as const

function CommunityGuide({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [page, setPage] = useState(0)
  const [title, body] = GUIDE[page]
  return (
    <Sheet
      open={open}
      onClose={() => {
        setPage(0)
        onClose()
      }}
      title="How Community works"
    >
      <p className="text-sm text-kicap-soft">
        Page {page + 1} of {GUIDE.length}
      </p>
      <div aria-live="polite">
        <h3 className="font-display text-xl font-semibold">{title}</h3>
        <p className="mt-2 text-kicap-soft">{body}</p>
      </div>
      <Button
        onClick={() => {
          if (page + 1 < GUIDE.length) setPage(page + 1)
          else {
            setPage(0)
            onClose()
          }
        }}
      >
        {page + 1 < GUIDE.length ? 'Next' : 'Got it'}
      </Button>
    </Sheet>
  )
}
