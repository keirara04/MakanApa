import { useEffect, useState } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { decideApi } from '../../api/decide'
import { ApiError } from '../../api/client'
import { errorMessage } from '../../lib/errors'
import { detailsLine } from '../../lib/presentation'
import { pushSupported } from '../../lib/push'
import { getStored, setStored } from '../../lib/storage'
import { NotificationPriming } from '../notifications/Notifications'
import { Button } from '../../ui/Button'
import { Mascot } from '../../ui/Mascot'
import { Sheet } from '../../ui/Sheet'
import { HalalBadge, Menu, Photos, Reasons, Reviews, SaveButton, SendToFriends, Tune, WhatIf, WhyNot } from './PickParts'
import { useDecide } from './state'
import { Rerolling, Thinking } from './Thinking'
import { HalalSection } from '../halal/Halal'

const FIT = { strong: 'Strong fit', good: 'Good fit', wildcard: 'Wildcard' } as const

export function Result() {
  const decide = useDecide()
  const navigate = useNavigate()

  if (decide.status === 'idle') return <Navigate to="/" replace />

  if (decide.status === 'thinking') {
    return (
      <Thinking
        asked={decide.asked}
        onCancel={() => {
          decide.reset()
          navigate('/')
        }}
      />
    )
  }

  if (decide.status === 'rerolling') return <Rerolling onCancel={decide.cancelReroll} />
  if (decide.status === 'error') return <Failure error={decide.error} onRetry={() => void decide.retry()} canRetry={decide.request !== null} />
  if (decide.status === 'none') return <NoResult />
  return <PickView />
}

function Failure({ error, onRetry, canRetry }: { error: unknown; onRetry: () => void; canRetry: boolean }) {
  const navigate = useNavigate()
  const offline = error instanceof ApiError && error.isNetwork
  const limited = error instanceof ApiError && error.status === 429
  return (
    <section className="flex flex-1 flex-col items-center justify-center gap-4 text-center">
      <Mascot mood="sad" size={140} />
      <h1 className="font-display text-3xl font-bold">{limited ? 'Slow down a bit' : 'Oops'}</h1>
      <p className="max-w-xs text-kicap-soft">
        {offline ? 'No connection. Couldn’t find food right now.' : limited ? errorMessage(error) : 'MakanApa hit a snag. Try again in a bit.'}
      </p>
      <div className="flex w-full max-w-xs flex-col gap-2">
        {canRetry && <Button onClick={onRetry}>Try again</Button>}
        <Button variant="quiet" onClick={() => navigate('/')}>
          Back home
        </Button>
      </div>
    </section>
  )
}

const RUNGS = [1, 2, 5, 10]

function NoResult() {
  const { request, asked, decide, source } = useDecide()
  const navigate = useNavigate()
  const km = request?.maxDistanceKm
  const wider = km === undefined ? undefined : RUNGS.find((rung) => rung > km)

  return (
    <section className="flex flex-1 flex-col items-center justify-center gap-4 text-center">
      <Mascot mood="sad" size={140} />
      <h1 className="font-display text-3xl font-bold">No spots found nearby</h1>
      <p className="max-w-sm text-kicap-soft">
        {km !== undefined
          ? `Nothing matched within ${km} km. Go a bit further, or loosen the filters.`
          : source === 'nearby'
            ? 'Nothing on the map fits right now. Try moving the map or clearing a filter.'
            : 'None of your saved places can be picked right now. They may be closed, or hidden by halal-only.'}
      </p>
      <div className="flex w-full max-w-xs flex-col gap-2">
        {request && asked && wider && (
          <Button onClick={() => void decide({ ...request, maxDistanceKm: wider }, { ...asked, distance: `Within ${wider} km` })}>Search within {wider} km</Button>
        )}
        <Button variant="quiet" onClick={() => navigate(request ? '/decide' : source === 'nearby' ? '/nearby' : '/')}>
          {request ? 'Change preferences' : source === 'nearby' ? 'Back to Nearby' : 'Back home'}
        </Button>
      </div>
    </section>
  )
}

function PickView() {
  const { decision, pick, asked, skipped, tunesUsed, widen, fresh, request, tune, choose, reroll, accept, decide, notice } = useDecide()
  const trace = fresh ? (pick?.thinkingTrace ?? []) : []
  const [tracing, setTracing] = useState(trace.length > 0)
  const [whatIfOpen, setWhatIfOpen] = useState(false)
  const [askTaste, setAskTaste] = useState(false)
  const [askPush, setAskPush] = useState(false)

  useEffect(() => {
    if (!tracing) return
    const timer = setTimeout(() => setTracing(false), Math.min(800, trace.length * 120 + 200) + 300)
    return () => clearTimeout(timer)
  }, [tracing, trace.length])

  if (!decision || !pick) return null

  if (tracing) {
    return (
      <section className="flex flex-1 flex-col justify-center gap-2" aria-live="polite">
        <h1 className="sr-only">Picking</h1>
        <ul className="mx-auto space-y-2 font-display text-xl font-semibold">
          {trace.map((line, index) => (
            <li key={line} className="trace-line flex items-center gap-3" style={{ ['--i' as string]: index }}>
              <span aria-hidden="true" className={index === trace.length - 1 ? 'text-pandan' : 'text-kicap-soft'}>
                {index === trace.length - 1 ? '✓' : '↓'}
              </span>
              {line}
            </li>
          ))}
        </ul>
      </section>
    )
  }

  const canTune = pick.canTune && tunesUsed.length < 2

  // One question per accepted pick, as on iOS: notifications first, then taste.
  function letsEat() {
    accept()
    if (!getStored('primingSeen') && pushSupported()) setAskPush(true)
    else if (!getStored('tasteAsked')) setAskTaste(true)
  }

  return (
    <article className="flex flex-col gap-6 pb-24 pt-2 md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:items-start md:gap-10 md:pb-28">
      {/* Taped in piece by piece: an animated translate on the article would trap the fixed action bar inside it. */}
      <div className="tape-in relative md:sticky md:top-12">
        <span className="tape -top-3 left-8 -rotate-6" aria-hidden="true" />
        <Photos pick={pick} />
      </div>

      <div className="tape-in flex flex-col gap-6">
        <header>
          <p className="text-sm font-semibold text-sambal-deep">
            {pick.fatigue ? 'Okay, enough choosing' : 'Picked for you'}
            {pick.fit && <span className="text-kicap-soft">, {FIT[pick.fit].toLowerCase()}</span>}
          </p>
          <div className="mt-1 flex items-start gap-3">
            <h1 className="flex-1 font-display text-4xl font-bold leading-tight tracking-tight">{pick.name}</h1>
            <SaveButton pick={pick} />
          </div>
          <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-kicap-soft">
            {detailsLine(pick).map((part) => (
              <li key={part}>{part}</li>
            ))}
          </ul>
          <div className="mt-3">
            <HalalBadge halal={pick.halal} />
          </div>
        </header>

        {notice && (
          <p role="alert" className="rounded-row bg-sambal/10 px-4 py-3 text-sambal-deep">
            Couldn’t get another one. {notice}
          </p>
        )}
        <Reasons pick={pick} asked={asked} onWhatIf={() => setWhatIfOpen(true)} />
        {skipped && <WhyNot key={pick.id} decision={decision} skipped={skipped} />}

        {/* Only when the server can widen: at the widest radius it would re-run the same search. */}
        {widen?.suggestedAdjustment && widen.canSearchWider && request ? (
          <section className="rounded-row bg-kunyit/25 px-4 py-4">
            <p>{widen.message ?? 'Nothing better nearby. Search a bit wider?'}</p>
            <Button
              variant="secondary"
              className="mt-3 md:max-w-xs"
              onClick={() =>
                void decide(
                  { ...request, maxDistanceKm: widen.suggestedAdjustment!.distanceKm, ...(widen.suggestedAdjustment!.budgetMax ? { budgetMax: widen.suggestedAdjustment!.budgetMax } : {}) },
                  { ...asked!, distance: `Within ${widen.suggestedAdjustment!.distanceKm} km` },
                )
              }
            >
              Search within {widen.suggestedAdjustment.distanceKm.toFixed(1)} km
            </Button>
          </section>
        ) : (
          <>
            {widen && !widen.canSearchWider && <p className="rounded-row bg-kunyit/25 px-4 py-3">{widen.message ?? 'Nothing better nearby.'}</p>}
            {canTune && <Tune used={tunesUsed} onTune={tune} />}
          </>
        )}

        <SendToFriends decision={decision} pick={pick} />
        <Menu pick={pick} />
        <HalalSection restaurantId={pick.id} name={pick.name} halal={pick.halal} />
        <Reviews pick={pick} />
        {(pick.reviews.length > 0 || pick.photos.some((photo) => photo.authorAttributions.length > 0)) && (
          <p className="text-xs text-kicap-soft">Photos and reviews from Google Maps</p>
        )}
      </div>

      {/* The three actions stay in reach: above the tab bar on phones, at the foot of the page on desktop. */}
      <div className="fixed inset-x-0 bottom-[calc(var(--tab-bar)+0.5rem)] z-10 px-5 md:sticky md:bottom-4 md:col-span-2 md:px-0">
        <div className="mx-auto flex max-w-3xl items-center gap-3 rounded-full bg-surface/95 p-2 shadow-lift backdrop-blur md:max-w-md">
          <Button onClick={letsEat} className="flex-1">
            Let’s eat
          </Button>
          <IconButton label="Another one" onClick={() => void reroll()}>
            <path d="M4 12a8 8 0 0 1 13.7-5.6L20 8M20 4v4h-4M20 12a8 8 0 0 1-13.7 5.6L4 16M4 20v-4h4" />
          </IconButton>
          <IconButton label="Not this" onClick={() => void reroll({ exclude: true })}>
            <path d="M7 14V4M7 14l-3 0V4h3M7 14l4.5 6a1.8 1.8 0 0 0 3.2-1.4L13.5 14H19a2 2 0 0 0 2-2.3l-1.2-6A2 2 0 0 0 17.8 4H7" />
          </IconButton>
        </div>
      </div>

      <WhatIf decision={decision} open={whatIfOpen} onClose={() => setWhatIfOpen(false)} onSwitch={choose} />
      <TastePrompt open={askTaste} onClose={() => setAskTaste(false)} />
      <NotificationPriming open={askPush} onClose={() => setAskPush(false)} />
    </article>
  )
}

function IconButton({ label, onClick, children }: { label: string; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" aria-label={label} title={label} onClick={onClick} className="press flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-kicap bg-surface">
      <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {children}
      </svg>
    </button>
  )
}

const TASTES = {
  Food: [
    ['malay', 'Malay'],
    ['mamak', 'Mamak'],
    ['korean', 'Korean'],
    ['cafe', 'Cafe'],
    ['dessert', 'Dessert'],
  ],
  Style: [
    ['cheap_eats', 'Cheap eats'],
    ['late_night', 'Late night'],
    ['anything', 'Anything'],
  ],
} as const

/** After the first accepted pick: "Want sharper picks?" Seeds Selera once. */
function TastePrompt({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [picked, setPicked] = useState<string[]>([])
  const [error, setError] = useState<string | null>(null)

  function finish() {
    setStored('tasteAsked', true)
    onClose()
  }

  function toggle(key: string) {
    if (key === 'anything') return setPicked(picked.includes('anything') ? [] : ['anything'])
    const rest = picked.filter((item) => item !== 'anything')
    setPicked(rest.includes(key) ? rest.filter((item) => item !== key) : rest.length < 4 ? [...rest, key] : rest)
  }

  return (
    <Sheet open={open} onClose={finish} title="Want sharper picks?">
      <p className="text-kicap-soft">Choose up to 4 things you usually go for. We’ll lean that way.</p>
      {Object.entries(TASTES).map(([group, options]) => (
        <fieldset key={group}>
          <legend className="font-display font-semibold">{group}</legend>
          <div className="mt-2 flex flex-wrap gap-2">
            {options.map(([key, label]) => (
              <button
                key={key}
                type="button"
                aria-pressed={picked.includes(key)}
                onClick={() => toggle(key)}
                className={`press min-h-11 rounded-full border-2 px-4 font-display font-semibold ${picked.includes(key) ? 'border-kicap bg-kicap text-cream' : 'border-hairline'}`}
              >
                {label}
              </button>
            ))}
          </div>
        </fieldset>
      ))}
      <p className="text-sm text-kicap-soft" aria-live="polite">
        {picked.length === 0 ? 'None picked yet' : `${picked.length} of 4 picked`}
      </p>
      {error && <p role="alert" className="text-sambal-deep">{error}</p>}
      <Button
        disabled={picked.length === 0}
        onClick={() => {
          decideApi
            .seedTaste(picked)
            .then(finish)
            .catch((caught) => setError(errorMessage(caught)))
        }}
      >
        Save
      </Button>
      <Button variant="quiet" onClick={finish}>
        Not now
      </Button>
    </Sheet>
  )
}
