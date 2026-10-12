import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { decideApi, type VibeTag } from '../../api/decide'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import { useLocation } from '../../lib/location'
import { directionsUrl } from '../../lib/maps'
import { budgetAmount, categoryLabel, distanceShort, mealGreeting, priceLabel, relativeDay } from '../../lib/presentation'
import { install, installPlatform, isInstalled, useCanInstall } from '../../lib/install'
import { getStored, setStored, useStored, type RecentPick, type SavedPlace } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { InstallGuide } from '../install/InstallGuide'
import { recordNudge } from '../notifications/useNudgeLinks'
import { ContextStrip } from './ContextStrip'
import { GuideDemo } from './GuideDemos'
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
        <div className="md:col-span-2">
          <QuickPickCard onLocationNeeded={() => void location.request()} />
        </div>
        <Link to="/decide" className="press flex min-h-28 items-center gap-3 rounded-card border border-hairline bg-surface p-5 shadow-sm">
          <span className="flex-1">
            <span className="block font-display text-xl font-bold">I know what I want</span>
            <span className="mt-0.5 block text-sm text-kicap-soft">Choose a craving, budget and distance</span>
          </span>
          <DishStack />
        </Link>
        <SavedShuffleCard saved={saved} />
      </div>

      <GuestNudge />
      <InstallCard />
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
        aria-label={busy ? 'Finding you…' : 'Pick for me'}
        aria-describedby="quick-pick-hint"
        className="press-raised relative flex w-full flex-col gap-4 overflow-hidden rounded-card bg-sambal p-6 text-left text-white"
      >
        <img
          src="/choices/mood-nasilemak.webp"
          alt=""
          width={104}
          height={104}
          className="absolute top-4 -right-7 h-26 w-26 rounded-full border-[5px] border-white/90 object-cover shadow-lg"
        />
        <span className="max-w-[calc(100%-5rem)] font-display text-3xl leading-tight font-bold tracking-tight">Can’t decide?</span>
        <span className="flex max-w-[calc(100%-4rem)] flex-wrap gap-1.5">
          {chips.map((chip) => (
            <span key={chip} className="rounded-full bg-kicap/30 px-2.5 py-1 text-sm font-semibold">
              {chip}
            </span>
          ))}
        </span>
        <span className="relative flex min-h-14 items-center justify-center gap-2.5 rounded-full bg-white font-display text-xl font-bold text-sambal-deep shadow-md">
          {/* The dice rolls while the pick is made. */}
          <svg viewBox="0 0 24 24" className={`h-6 w-6 ${busy ? 'spoon-spin' : ''}`} fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
            <rect x="3.5" y="3.5" width="17" height="17" rx="4" />
            <circle cx="8.5" cy="8.5" r="1.4" fill="currentColor" stroke="none" />
            <circle cx="15.5" cy="8.5" r="1.4" fill="currentColor" stroke="none" />
            <circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none" />
            <circle cx="8.5" cy="15.5" r="1.4" fill="currentColor" stroke="none" />
            <circle cx="15.5" cy="15.5" r="1.4" fill="currentColor" stroke="none" />
          </svg>
          {busy ? 'Finding you…' : 'Pick for me'}
        </span>
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

const DISHES = ['mood-charkueyteow', 'mood-dimsum', 'mood-nasikandar']

/** Three cravings overlapping, the kind of choice the next screen offers. */
function DishStack() {
  return (
    <span className="flex shrink-0" aria-hidden="true">
      {DISHES.map((dish, index) => (
        <img
          key={dish}
          src={`/choices/${dish}.webp`}
          alt=""
          width={40}
          height={40}
          className={`h-10 w-10 rounded-full border-[3px] border-surface object-cover ${index > 0 ? '-ml-4' : ''}`}
        />
      ))}
    </span>
  )
}

const DECK_TINTS = ['bg-kunyit text-kicap', 'bg-pandan text-white', 'bg-sambal text-white']

/**
 * Always shown, so people learn the shuffle exists. With two or more saved places it opens the
 * deck; before that it shows how many are saved and sends them to Nearby to save some.
 */
export function SavedShuffleCard({ saved }: { saved: SavedPlace[] }) {
  const ready = saved.length >= 2
  const deck: (SavedPlace | null)[] = ready ? saved.slice(0, 3) : [null, null, saved[0] ?? null]

  return (
    <Link
      to={ready ? '/decide/saved' : '/nearby'}
      className="press flex min-h-28 items-center gap-4 rounded-card border border-hairline bg-surface p-5 shadow-sm"
    >
      <span className="flex-1">
        <span className="block font-display text-xl font-bold">{ready ? 'Pick from my saved' : 'Shuffle your saved places'}</span>
        <span className="mt-0.5 block text-sm text-kicap-soft">
          {ready
            ? `Shuffle your ${saved.length} saved places`
            : saved.length === 1
              ? 'Save one more place and MakanApa can pick between them.'
              : 'Save two places from Nearby and MakanApa deals you one.'}
        </span>
      </span>
      {/* A small fanned deck of saved places; until two are saved, empty outlines sit behind the one saved. */}
      <span className="relative mr-2 h-14 w-16 shrink-0" aria-hidden="true">
        {deck.map((place, slot) => {
          const offset = slot - (deck.length - 1) / 2
          return (
            <span
              key={slot}
              style={{ rotate: `${offset * 12}deg`, translate: `${offset * 10}px 0` }}
              className={`absolute inset-x-3 inset-y-0 flex origin-bottom items-center justify-center rounded-lg font-display text-lg font-bold ${
                place ? `${DECK_TINTS[slot]} shadow-md` : 'border-2 border-dashed border-kicap/30 bg-cream text-kicap/40'
              }`}
            >
              {place ? place.name.trim().charAt(0).toUpperCase() : '+'}
            </span>
          )
        })}
      </span>
    </Link>
  )
}

const WEEK = 7 * 86_400_000

const MONTH = 30 * 86_400_000

/**
 * "Add to home screen": one tap where the browser can install (Chrome, Edge), the how-to guide on
 * phones that can't (Safari on iPhone never offers it). Gone once installed; "Not now" hides it
 * for a month.
 */
function InstallCard() {
  const canInstall = useCanInstall()
  const dismissedAt = useStored('installDismissedAt') ?? 0
  const [now] = useState(() => Date.now())
  const [phone] = useState(() => installPlatform() !== 'computer')
  const [guide, setGuide] = useState(false)
  if (isInstalled() || (!canInstall && !phone) || now - dismissedAt < MONTH) return null

  return (
    <section className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-5 shadow-sm sm:flex-row sm:items-center">
      <img src="/icon-192.png" alt="" width={56} height={56} className="h-14 w-14 shrink-0 rounded-2xl" />
      <div className="flex-1">
        <h2 className="font-display text-xl font-bold">Add MakanApa to your home screen</h2>
        <p className="text-kicap-soft">It opens like an app, full screen, with mealtime notifications. No app store needed.</p>
      </div>
      <div className="flex shrink-0 items-center gap-1">
        <Button className="sm:w-auto" onClick={() => (canInstall ? void install() : setGuide(true))}>
          {canInstall ? 'Add' : 'Show me how'}
        </Button>
        <Button variant="quiet" className="whitespace-nowrap" onClick={() => setStored('installDismissedAt', Date.now())}>
          Not now
        </Button>
      </div>
      <InstallGuide open={guide} onClose={() => setGuide(false)} />
    </section>
  )
}

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
    <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-5 shadow-sm">
      <div className="flex items-start gap-4">
        <Mascot mood="excited" size={56} className="h-14 w-14 shrink-0" />
        <div>
          <h2 className="font-display text-xl font-bold">{copy.title}</h2>
          <p className="mt-1 text-sm text-kicap-soft">{copy.detail}</p>
        </div>
      </div>
      <div className="flex items-center gap-2">
        <Button className="min-h-12 flex-1 px-4 text-base whitespace-nowrap" onClick={() => navigate('/sign-in')}>
          Create an account
        </Button>
        <Button
          variant="quiet"
          className="whitespace-nowrap"
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
        <p className="mt-2 rounded-card border border-dashed border-kicap/25 px-5 py-4 text-kicap-soft">Places you pick will show up here.</p>
      ) : (
        <ul className="mt-3 divide-y divide-hairline overflow-hidden rounded-card border border-hairline bg-surface shadow-sm">
          {recent.map((item) => item.kind === 'kakao' ? (
            // Kakao's terms allow keeping only the link, not the place's name.
            <li key={item.id} className="flex items-center gap-1 pr-2">
              <a href={item.kakaoUrl} target="_blank" rel="noreferrer" className="flex min-h-18 flex-1 items-center gap-3 py-3 pl-4">
                <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-row bg-cream font-display text-lg font-bold text-sambal-deep">
                  K
                </span>
                <span className="min-w-0">
                  <span className="block truncate font-display text-lg font-semibold">A place on Kakao Map</span>
                  <span className="block text-sm text-kicap-soft">{relativeDay(item.at)}</span>
                </span>
              </a>
              <button
                type="button"
                onClick={() => remove(item)}
                aria-label="Remove this Kakao Map place from Recent"
                className="press flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-kicap-soft/70 hover:bg-cream"
              >
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" aria-hidden="true">
                  <path d="M6 6l12 12M18 6 6 18" />
                </svg>
              </button>
            </li>
          ) : (
            <li key={item.id} className="flex items-center gap-1 pr-2">
              <a
                href={directionsUrl(item, getStored('mapsApp'))}
                target="_blank"
                rel="noreferrer"
                className="flex min-h-18 flex-1 items-center gap-3 py-3 pl-4"
              >
                <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-row bg-cream font-display text-lg font-bold text-sambal-deep">
                  {item.name.trim().charAt(0).toUpperCase()}
                </span>
                <span className="min-w-0">
                  <span className="block truncate font-display text-lg font-semibold">{item.name}</span>
                  <span className="block text-sm text-kicap-soft">
                    {[categoryLabel(item.foodCategory), priceLabel(item.priceLevel), relativeDay(item.at)].filter(Boolean).join(', ')}
                  </span>
                </span>
              </a>
              <button
                type="button"
                onClick={() => remove(item)}
                aria-label={`Remove ${item.name} from Recent`}
                className="press flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-kicap-soft/70 hover:bg-cream"
              >
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" aria-hidden="true">
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
        className="press flex min-h-18 items-center gap-4 rounded-card border border-hairline bg-surface px-5 py-3 text-left shadow-sm"
      >
        <span aria-hidden="true" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-kunyit/30 font-display text-xl font-bold">
          ?
        </span>
        <span className="flex-1">
          <span className="block font-display text-lg font-semibold">New to MakanApa?</span>
          <span className="text-sm text-kicap-soft">See how picking works.</span>
        </span>
        <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="m9 6 6 6-6 6" />
        </svg>
      </button>
      <Sheet open={open} onClose={() => setOpen(false)} title="How Decide works">
        <GuideDemo key={page} page={page} />
        <div aria-live="polite">
          <h3 className="font-display text-xl font-semibold">{title}</h3>
          <p className="mt-1 min-h-18 text-kicap-soft">{body}</p>
        </div>
        <div className="flex items-center justify-center gap-1.5" aria-label={`Page ${page + 1} of ${GUIDE.length}`} role="img">
          {GUIDE.map(([name], index) => (
            <span key={name} className={`h-2 rounded-full transition-[width] motion-reduce:transition-none ${index === page ? 'w-6 bg-kicap' : 'w-2 bg-hairline'}`} />
          ))}
        </div>
        <div className="flex items-center gap-2">
          {page > 0 && (
            <Button variant="secondary" className="flex-1" onClick={() => setPage(page - 1)}>
              Back
            </Button>
          )}
          <Button className="flex-1" onClick={() => (page + 1 < GUIDE.length ? setPage(page + 1) : setOpen(false))}>
            {page + 1 < GUIDE.length ? 'Next' : 'Got it'}
          </Button>
        </div>
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
