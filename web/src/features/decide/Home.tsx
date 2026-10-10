import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { decideApi, type VibeTag } from '../../api/decide'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { directionsUrl } from '../../lib/maps'
import { budgetAmount, categoryLabel, distanceShort, mealGreeting, priceLabel, relativeDay } from '../../lib/presentation'
import { getStored, setStored, useStored, type RecentPick } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import { recordNudge } from '../notifications/useNudgeLinks'
import { ContextStrip } from './ContextStrip'
import { buildSoloRequest, LocationNeeded, quickPickAnswers } from './request'
import { useDecide } from './state'

export function DecideHome() {
  const now = new Date()
  const location = useLocation()
  const coords = location.state.status === 'ready' ? location.state.coords : null
  const saved = useStored('saved') ?? []

  return (
    <div className="flex flex-col gap-8 pt-2">
      <header>
        <p className="text-kicap-soft">{now.toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long' })}</p>
        <h1 className="mt-1 font-display text-5xl font-bold tracking-tight md:text-6xl">{mealGreeting(now.getHours())}</h1>
      </header>

      <ContextStrip coords={coords} />

      {location.state.status === 'denied' && (
        <p role="status" className="rounded-row border-2 border-hairline bg-surface px-4 py-3">
          Location is blocked for this site, so MakanApa can’t pick nearby. Allow Location from the icon next to the address bar, then
          reload.
        </p>
      )}

      <div className="grid gap-4 md:grid-cols-2">
        <QuickPickCard onLocationNeeded={() => void location.request()} />
        <Link to="/decide" className="press flex min-h-36 flex-col justify-between rounded-card border-2 border-kicap bg-surface p-6">
          <span className="font-display text-sm font-semibold text-kicap-soft">Craving, budget, distance</span>
          <span className="font-display text-3xl font-bold tracking-tight">I know what I want</span>
        </Link>
        {saved.length >= 2 && (
          <Link to="/decide/saved" className="press flex min-h-28 flex-col justify-between rounded-card border-2 border-dashed border-kicap/40 p-6 md:col-span-2">
            <span className="font-display text-sm font-semibold text-kicap-soft">Shuffle your {saved.length} saved places</span>
            <span className="font-display text-2xl font-bold tracking-tight">Pick from my saved</span>
          </Link>
        )}
      </div>

      <GuestNudge />
      <Recent />
      <DecideGuide />
      <VibeFollowUp />
    </div>
  )
}

function QuickPickCard({ onLocationNeeded }: { onLocationNeeded: () => void }) {
  const { user } = useSession()
  const { decide } = useDecide()
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const answers = quickPickAnswers()
  const chips = ['Anything', budgetAmount(answers.budgetMax) ?? 'Any budget', distanceShort(answers.maxDistanceKm)]
  const [params, setParams] = useSearchParams()

  // Opened from a mealtime push that named no place: straight into the pick, as on iOS.
  const fromPush = params.get('quickPick')
  const started = useRef<string | null>(null)
  useEffect(() => {
    if (!fromPush || started.current === fromPush) return
    started.current = fromPush
    setParams({}, { replace: true })
    recordNudge(fromPush, 'quick_pick_started')
    void pick()
    // pick reads the latest answers when it runs; this is for the push only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fromPush])

  async function pick() {
    setBusy(true)
    setError(null)
    try {
      const { request, asked } = await buildSoloRequest(answers, user)
      void decide(request, { ...asked, budget: budgetAmount(answers.budgetMax) ?? 'No limits' })
      navigate('/decide/result')
    } catch (caught) {
      if (caught instanceof LocationNeeded) {
        setError('MakanApa needs your location to pick somewhere nearby.')
        onLocationNeeded()
      } else {
        setError(errorMessage(caught))
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <button
        type="button"
        onClick={() => void pick()}
        disabled={busy}
        aria-describedby="quick-pick-hint"
        className="press flex min-h-36 w-full flex-col justify-between gap-4 rounded-card bg-sambal p-6 text-left text-white shadow-lift disabled:opacity-70"
      >
        <span className="flex flex-wrap gap-1.5">
          {chips.map((chip) => (
            <span key={chip} className="rounded-full bg-white/20 px-2.5 py-0.5 text-sm font-semibold">
              {chip}
            </span>
          ))}
        </span>
        <span className="font-display text-3xl font-bold tracking-tight">{busy ? 'Finding you…' : 'Pick for me'}</span>
      </button>
      <p id="quick-pick-hint" className="sr-only">
        Picks somewhere nearby right away using your usual budget and distance
      </p>
      {error && (
        <p role="alert" className="text-sm text-sambal-deep">
          {error}
        </p>
      )}
    </div>
  )
}

const WEEK = 7 * 86_400_000

/** The iOS GuestUpgradeNudge: after 3 accepted picks or 3 saves, guests are asked to keep them. */
function GuestNudge() {
  const { user } = useSession()
  const navigate = useNavigate()
  const picks = useStored('nudgePicks') ?? []
  const saves = (useStored('saved') ?? []).length
  const dismissed = useStored('nudgeDismissed') ?? []
  const snoozedUntil = useStored('nudgeSnoozedUntil') ?? 0

  if (!user?.isGuest || Date.now() < snoozedUntil) return null
  const reason = picks.length >= 3 && !dismissed.includes('picks') ? 'picks' : saves >= 3 && !dismissed.includes('saves') ? 'saves' : null
  if (!reason) return null

  const copy =
    reason === 'picks'
      ? { title: 'You’ve been picking well', detail: 'MakanApa is learning what you like. Save it to an account so a new phone doesn’t mean starting over.' }
      : { title: 'Your food list is growing', detail: 'Keep your saved places safe, on this phone or the next one.' }

  return (
    <section className="sketch px-6 py-6" style={{ ['--sketch-radius' as string]: '22px' }}>
      <h2 className="font-display text-2xl font-bold">{copy.title}</h2>
      <p className="mt-2 text-kicap-soft">{copy.detail}</p>
      <p className="mt-2 text-kicap-soft">Your picks, saved places and taste profile all come with you. Nothing lost.</p>
      <div className="mt-5 flex flex-wrap items-center gap-2">
        <Button className="md:max-w-xs" onClick={() => navigate('/sign-in')}>
          Create an account
        </Button>
        <Button
          variant="quiet"
          onClick={() => {
            setStored('nudgeDismissed', [...dismissed, reason])
            setStored('nudgeSnoozedUntil', Date.now() + WEEK)
          }}
        >
          Not now
        </Button>
      </div>
    </section>
  )
}

function Recent() {
  const recent = (useStored('recent') ?? []).slice(0, 3)

  function remove(item: RecentPick) {
    setStored('recent', (getStored('recent') ?? []).filter((entry) => entry.id !== item.id))
  }

  return (
    <section aria-labelledby="recent-title">
      <h2 id="recent-title" className="font-display text-2xl font-bold">
        Recent
      </h2>
      {recent.length === 0 ? (
        <p className="mt-2 text-kicap-soft">Places you pick will show up here.</p>
      ) : (
        <ul className="mt-3 divide-y-2 divide-hairline border-y-2 border-hairline">
          {recent.map((item) => (
            <li key={item.id} className="flex items-center gap-3">
              <a
                href={directionsUrl(item, getStored('mapsApp'))}
                target="_blank"
                rel="noreferrer"
                className="flex min-h-16 flex-1 flex-col justify-center py-3"
              >
                <span className="font-display text-lg font-semibold">{item.name}</span>
                <span className="text-sm text-kicap-soft">
                  {[categoryLabel(item.foodCategory), priceLabel(item.priceLevel), relativeDay(item.at)].filter(Boolean).join(', ')}
                </span>
              </a>
              <button
                type="button"
                onClick={() => remove(item)}
                aria-label={`Remove ${item.name} from Recent`}
                className="press flex h-11 w-11 items-center justify-center rounded-full text-kicap-soft hover:bg-surface"
              >
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                  <path d="M6 6l12 12M18 6 6 18" />
                </svg>
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

const GUIDE = [
  ['Pick for me', 'One tap and MakanApa picks somewhere nearby, using the budget and distance you used last time.'],
  ['I know what I want', 'Choose a mood or type a craving, set your budget and distance, and MakanApa picks for you.'],
  ['See why it won', 'Every pick tells you why it was chosen. Tap Let’s eat for directions, or ask for another one.'],
  ['Nudge the pick', 'Not quite right? Tap Closer, Cheaper or Safer bet and it picks again from the same list.'],
  ['Pick from your saved places', 'Save two or more places and MakanApa shuffles them like a deck of cards and deals you one.'],
  ['Go back to a favourite', 'Places you pick show up in Recent. Tap one to open it in your maps app.'],
] as const

function DecideGuide() {
  const [open, setOpen] = useState(false)
  const [page, setPage] = useState(0)
  const [title, body] = GUIDE[page]

  return (
    <>
      <button
        type="button"
        onClick={() => {
          setPage(0)
          setOpen(true)
        }}
        className="press flex min-h-16 items-center justify-between rounded-row border-2 border-hairline px-5 py-3 text-left"
      >
        <span>
          <span className="block font-display text-lg font-semibold">New to MakanApa?</span>
          <span className="text-sm text-kicap-soft">See how picking works.</span>
        </span>
        <span aria-hidden="true" className="font-hand text-3xl text-sambal-deep">
          ?
        </span>
      </button>
      <Sheet open={open} onClose={() => setOpen(false)} title="How Decide works">
        <p className="text-sm text-kicap-soft">
          Page {page + 1} of {GUIDE.length}
        </p>
        <div aria-live="polite">
          <h3 className="font-display text-xl font-semibold">{title}</h3>
          <p className="mt-2 text-kicap-soft">{body}</p>
        </div>
        <Button onClick={() => (page + 1 < GUIDE.length ? setPage(page + 1) : setOpen(false))}>{page + 1 < GUIDE.length ? 'Next' : 'Got it'}</Button>
      </Sheet>
    </>
  )
}

const VIBES: [VibeTag, string][] = [
  ['chill', 'Chill'],
  ['study', 'Study'],
  ['student_budget', 'Student'],
  ['late_night', 'Late night'],
  ['hidden_gem', 'Hidden gem'],
]
const HOUR = 3_600_000

/** An hour or more after an accepted pick (every second one), ask what it was like. Dropped after a day. */
function VibeFollowUp() {
  const pending = useStored('pendingVibe')
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!pending) return
    const age = Date.now() - pending.acceptedAt
    if (age > 24 * HOUR) setStored('pendingVibe', undefined)
    else if (age >= HOUR) setOpen(true)
  }, [pending])

  if (!pending) return null

  function finish() {
    setOpen(false)
    setStored('pendingVibe', undefined)
  }

  return (
    <Sheet open={open} onClose={finish} title={`How was ${pending.restaurantName}?`}>
      <p className="text-kicap-soft">Pick the vibe. It helps the next person decide.</p>
      <div className="flex flex-wrap gap-2">
        {VIBES.map(([tag, label]) => (
          <button
            key={tag}
            type="button"
            className="press min-h-11 rounded-full border-2 border-kicap bg-cream px-4 font-display font-semibold"
            onClick={() => {
              decideApi
                .vibeTag(pending, tag)
                .then(finish)
                .catch((caught) => setError(errorMessage(caught)))
            }}
          >
            {label}
          </button>
        ))}
      </div>
      {error && (
        <p role="alert" className="text-sm text-sambal-deep">
          {error}
        </p>
      )}
      <Button variant="quiet" onClick={finish}>
        Skip
      </Button>
    </Sheet>
  )
}
