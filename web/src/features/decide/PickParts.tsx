import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router'
import { PhotoViewer, viewerPhotos } from '../../ui/PhotoViewer'
import type { AddPlaceState } from '../community/AddPlace'
import { decideApi, type Decision, type Recommendation, type TuneDirection, type WhatIfRow, type WhyNotDetail, type WhyNotReason } from '../../api/decide'
import { errorMessage } from '../../lib/errors'
import { kmLabel, menuPrice } from '../../lib/presentation'
import { getStored, setStored, useStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import type { AskedFor } from './state'

/** Swipeable photos with the rating; the headline ("NASI LEMAK.") stands in when there are none. */
export function Photos({ pick }: { pick: Recommendation }) {
  const [viewing, setViewing] = useState<number | null>(null)
  return (
    <div className="relative">
      {pick.photos.length > 0 ? (
        <div className="flex aspect-[4/3] snap-x snap-mandatory overflow-x-auto rounded-card bg-hairline [scrollbar-width:none]" tabIndex={0} aria-label={`Photos of ${pick.name}`}>
          {pick.photos.map((photo, index) => (
            <button key={photo.url} type="button" onClick={() => setViewing(index)} aria-label={`Open photo ${index + 1} of ${pick.photos.length} full screen`} className="h-full w-full shrink-0 snap-center">
              <img src={photo.url} alt={index === 0 ? `${pick.name}` : ''} loading={index === 0 ? 'eager' : 'lazy'} className="h-full w-full object-cover" />
            </button>
          ))}
        </div>
      ) : (
        <div className="flex aspect-[4/3] items-center justify-center rounded-card bg-kunyit/30 px-6">
          <p className="text-center font-display text-5xl font-bold tracking-tight text-kicap/80">{pick.headline}</p>
        </div>
      )}
      {pick.rating !== null && (
        <span className="absolute bottom-3 right-3 rounded-full bg-surface px-3 py-1 font-display font-semibold shadow-lift">
          <span aria-hidden="true">★ </span>
          {pick.rating.toFixed(1)}
          <span className="sr-only"> out of 5 on Google</span>
        </span>
      )}
      {pick.photos.length > 1 && (
        <span className="absolute bottom-3 left-3 rounded-full bg-kicap/70 px-2.5 py-0.5 text-sm text-white" aria-hidden="true">
          {pick.photos.length} photos, swipe
        </span>
      )}
      {viewing !== null && <PhotoViewer photos={viewerPhotos(pick.photos)} start={viewing} title={pick.name} onClose={() => setViewing(null)} />}
    </div>
  )
}

export { HalalBadge } from '../../ui/HalalBadge'

/** Heart toggle: saves on this device and on the account, like iOS's PlacePreferencesStore. */
export function SaveButton({ pick }: { pick: Recommendation }) {
  const saved = (useStored('saved') ?? []).some((place) => place.id === pick.id)

  function toggle() {
    const list = getStored('saved') ?? []
    if (saved) {
      setStored('saved', list.filter((place) => place.id !== pick.id))
      void decideApi.unsave(pick.id).catch(() => {})
    } else {
      setStored('saved', [{ id: pick.id, name: pick.name, latitude: pick.latitude, longitude: pick.longitude, rating: pick.rating, priceLevel: pick.priceLevel, savedAt: Date.now() }, ...list])
      void decideApi.save(pick.id).catch(() => {})
    }
  }

  return (
    <button
      type="button"
      aria-pressed={saved}
      onClick={toggle}
      className="press flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-kicap bg-surface"
      aria-label={saved ? `Remove ${pick.name} from saved` : `Save ${pick.name}`}
    >
      <svg viewBox="0 0 24 24" className={`h-6 w-6 ${saved ? 'fill-sambal text-sambal' : 'fill-none'}`} stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
      </svg>
    </button>
  )
}

const FAMILY_ICON: Record<string, string> = { match: 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z', edge: 'M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0zM5 5H3v2a3 3 0 0 0 3 3M19 5h2v2a3 3 0 0 1-3 3', moment: 'M12 7v5l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z' }

/** "Why this one?" with the Brain's reasons, or the v1 echo of what was asked. */
export function Reasons({ pick, asked, onWhatIf }: { pick: Recommendation; asked: AskedFor | null; onWhatIf: () => void }) {
  if (pick.reasons?.length) {
    return (
      <section className="sketch px-5 py-5" style={{ ['--sketch-radius' as string]: '20px' }} aria-labelledby="why-title">
        <h2 id="why-title" className="font-display text-xl font-bold">
          Why this one?
        </h2>
        <ul className="mt-3 space-y-2.5">
          {pick.reasons.map((reason, index) => (
            <li key={reason.key} className="trace-line flex gap-3" style={{ ['--i' as string]: index }}>
              <svg viewBox="0 0 24 24" className="mt-0.5 h-5 w-5 shrink-0 text-sambal" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d={FAMILY_ICON[reason.family] ?? FAMILY_ICON.match} />
              </svg>
              <span>{reason.text}</span>
            </li>
          ))}
        </ul>
        {pick.decidingFactor && <p className="mt-3 text-sm text-kicap-soft">{pick.decidingFactor}</p>}
        {pick.hasWhatIf && (
          <Button variant="quiet" className="-ml-3 mt-1" onClick={onWhatIf}>
            What mattered most?
          </Button>
        )}
      </section>
    )
  }

  if (!asked) return null
  return (
    <section aria-labelledby="why-title">
      <h2 id="why-title" className="font-display text-lg font-semibold">
        Why this?
      </h2>
      <ul className="mt-2 flex flex-wrap gap-2">
        {[asked.mood, asked.budget, asked.distance].map((chip) => (
          <li key={chip} className="rounded-full border-2 border-hairline px-3 py-1 text-sm font-semibold">
            {chip}
          </li>
        ))}
      </ul>
    </section>
  )
}

const WHY_NOT: [WhyNotReason, string][] = [
  ['too_far', 'Too far'],
  ['too_pricey', 'Too pricey'],
  ['not_feeling_it', 'Not feeling it'],
  ['ate_recently', 'Ate recently'],
]
const WHY_NOT_DETAIL: [WhyNotDetail, string][] = [
  ['too_heavy', 'Too heavy'],
  ['too_similar', 'Too similar'],
  ['dont_like_cuisine', 'Don’t like this cuisine'],
  ['just_not_today', 'Just not today'],
]

/** After "Another one": "Skipped X. Help me learn: why not this one?" Fire and forget. */
export function WhyNot({ decision, skipped }: { decision: Decision; skipped: string }) {
  const [state, setState] = useState<'ask' | 'detail' | 'done'>('ask')

  function send(reason: WhyNotReason, detail?: WhyNotDetail) {
    void decideApi.whyNot(decision, reason, detail).catch(() => {})
    setState('done')
  }

  if (state === 'done') return <p className="text-sm text-kicap-soft" role="status">Noted, thanks</p>

  return (
    <section className="rounded-row border-2 border-dashed border-hairline px-4 py-4">
      <p className="text-sm text-kicap-soft">Skipped {skipped}</p>
      <p className="font-display font-semibold">Help me learn: why not this one?</p>
      <div className="mt-3 flex flex-wrap gap-2">
        {(state === 'ask' ? WHY_NOT : WHY_NOT_DETAIL).map(([key, label]) => (
          <button
            key={key}
            type="button"
            className="press min-h-10 rounded-full border-2 border-hairline bg-surface px-3 text-sm font-semibold"
            onClick={() => (key === 'not_feeling_it' ? setState('detail') : state === 'detail' ? send('not_feeling_it', key as WhyNotDetail) : send(key as WhyNotReason))}
          >
            {label}
          </button>
        ))}
        {state === 'detail' && (
          <button type="button" className="min-h-10 px-3 text-sm font-semibold text-kicap-soft underline" onClick={() => send('not_feeling_it')}>
            Skip
          </button>
        )}
      </div>
    </section>
  )
}

const TUNES: [TuneDirection, string][] = [
  ['closer', 'Closer'],
  ['cheaper', 'Cheaper'],
  ['safer', 'Safer bet'],
  ['adventurous', 'More adventurous'],
]

/** "Not quite?" Up to two nudges per decision. Only on Brain decisions that allow it. */
export function Tune({ used, onTune }: { used: TuneDirection[]; onTune: (direction: TuneDirection) => Promise<void> }) {
  const [busy, setBusy] = useState<TuneDirection | null>(null)
  const [error, setError] = useState<string | null>(null)

  return (
    <section>
      <h2 className="font-display text-lg font-semibold">Not quite?</h2>
      <div className="mt-2 flex flex-wrap gap-2">
        {TUNES.map(([direction, label]) => {
          const done = used.includes(direction)
          return (
            <button
              key={direction}
              type="button"
              disabled={done || busy !== null || used.length >= 2}
              aria-busy={busy === direction || undefined}
              onClick={() => {
                setBusy(direction)
                setError(null)
                onTune(direction)
                  .catch((caught) => setError(errorMessage(caught)))
                  .finally(() => setBusy(null))
              }}
              className={`press min-h-11 rounded-full border-2 px-4 font-display font-semibold disabled:cursor-default ${done ? 'border-sambal-deep bg-sambal-deep text-white' : 'border-kicap bg-surface disabled:opacity-50'}`}
            >
              {label}
            </button>
          )
        })}
      </div>
      {error && <p role="alert" className="mt-2 text-sm text-sambal-deep">{error}</p>}
    </section>
  )
}

/** "What mattered most?": each line removes one factor and names who would have won. */
export function WhatIf({ decision, open, onClose, onSwitch }: { decision: Decision; open: boolean; onClose: () => void; onSwitch: (id: number) => Promise<void> }) {
  const [rows, setRows] = useState<WhatIfRow[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    setRows(null)
    setError(null)
    void decideApi.interaction(decision, 'reasons_expanded').catch(() => {})
    decideApi
      .whatIf(decision)
      .then((response) => !cancelled && setRows(response.whatIf))
      .catch((caught) => !cancelled && setError(errorMessage(caught)))
    return () => {
      cancelled = true
    }
  }, [open, decision])

  return (
    <Sheet open={open} onClose={onClose} title="What mattered most?">
      <p className="text-kicap-soft">Each line removes one thing I weighed, and shows who would’ve won instead.</p>
      {error && <p role="alert" className="text-sambal-deep">{error}</p>}
      {!rows && !error && <p role="status">Working it out…</p>}
      {rows?.length === 0 && <p>Nothing would change it. This one wins on every count.</p>}
      {rows && rows.length > 0 && (
        <ul className="divide-y-2 divide-hairline">
          {rows.map((row) => (
            <li key={row.component} className="flex items-center gap-3 py-3">
              <span className="flex-1">
                <span className="block text-sm text-kicap-soft">{row.label}</span>
                <span className="font-display text-lg font-semibold">{row.winner.name ?? 'Another place'}</span>
              </span>
              <button
                type="button"
                className="press min-h-11 rounded-full border-2 border-kicap bg-cream px-4 font-display font-semibold"
                onClick={() => void onSwitch(row.winner.id).then(onClose).catch((caught) => setError(errorMessage(caught)))}
              >
                Switch
              </button>
            </li>
          ))}
        </ul>
      )}
      <Button variant="quiet" onClick={onClose}>
        Done
      </Button>
    </Sheet>
  )
}

/** WhatsApp first (most of Malaysia), then the system share sheet or a copied link. */
export function SendToFriends({ decision, pick }: { decision: Decision; pick: Recommendation }) {
  const [copied, setCopied] = useState(false)
  const category = pick.foodCategory ? `, ${pick.foodCategory.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())}` : ''
  const message = `Want to eat here? ${pick.name}${category} (${kmLabel(pick.distanceKm)})`
  const record = () => {
    void decideApi.interaction(decision, 'shared').catch(() => {})
    void decideApi.shareEvent(pick.id).catch(() => {})
  }

  async function share() {
    record()
    if (navigator.share) {
      await navigator.share({ title: pick.name, text: message, url: pick.shareUrl }).catch(() => {})
    } else {
      await navigator.clipboard?.writeText(`${message}\n${pick.shareUrl}`)
      setCopied(true)
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <a
        href={`https://wa.me/?text=${encodeURIComponent(`${message}\n${pick.shareUrl}`)}`}
        target="_blank"
        rel="noreferrer"
        onClick={record}
        className="press inline-flex min-h-12 items-center rounded-full border-2 border-kicap bg-surface px-5 font-display font-semibold"
      >
        Send to friends
      </a>
      <button type="button" onClick={() => void share()} className="press inline-flex min-h-12 items-center rounded-full px-4 font-display font-semibold text-sambal-deep underline decoration-2 underline-offset-4">
        {copied ? 'Link copied' : 'More ways to share'}
      </button>
    </div>
  )
}

export function Menu({ pick }: { pick: Recommendation }) {
  const { pathname } = useLocation()
  if (pick.menuItems.length === 0) {
    const handover: AddPlaceState = {
      existing: { id: pick.id, name: pick.name, address: null, foodCategory: pick.foodCategory, priceLevel: pick.priceLevel, latitude: pick.latitude, longitude: pick.longitude, distanceKm: null },
      menu: true,
      from: pathname,
    }
    return (
      <Link to="/community/add" state={handover} className="press flex min-h-14 items-center justify-between rounded-row border-2 border-hairline bg-surface px-4 font-semibold">
        Know the menu? Add it
        <svg viewBox="0 0 24 24" className="h-5 w-5 text-kicap-soft" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true">
          <path d="m9 6 6 6-6 6" />
        </svg>
      </Link>
    )
  }
  return (
    <section aria-labelledby="menu-title">
      <h2 id="menu-title" className="font-display text-xl font-bold">
        Potential menu
      </h2>
      <ul className="mt-2 divide-y divide-hairline">
        {pick.menuItems.map((item) => (
          <li key={`${item.name}-${item.price}`} className="flex justify-between gap-4 py-2">
            <span>
              <span className="font-semibold">{item.name}</span>
              {item.description && <span className="block text-sm text-kicap-soft">{item.description}</span>}
            </span>
            {item.price !== null && <span className="shrink-0 font-semibold">{menuPrice(item.price, pick)}</span>}
          </li>
        ))}
      </ul>
      <p className="mt-2 text-sm text-kicap-soft">Shared by the MakanApa community. It may not be complete or up to date.</p>
    </section>
  )
}

export function Reviews({ pick }: { pick: Recommendation }) {
  if (pick.reviews.length === 0) return null
  return (
    <section aria-labelledby="reviews-title">
      <h2 id="reviews-title" className="font-display text-xl font-bold">
        Reviews from Google Maps
      </h2>
      <ul className="mt-3 space-y-4">
        {pick.reviews.map((review, index) => (
          <li key={index}>
            {review.rating !== null && (
              <p className="text-kunyit" aria-label={`${review.rating} out of 5`}>
                <span aria-hidden="true">{'★'.repeat(Math.round(review.rating))}</span>
              </p>
            )}
            {review.text && <p className="italic">{review.text.length > 160 ? `${review.text.slice(0, 160)}…` : review.text}</p>}
            <p className="mt-1 text-sm text-kicap-soft">
              {review.authorName}
              {review.relativePublishTime && `, ${review.relativePublishTime}`}
            </p>
          </li>
        ))}
      </ul>
      {pick.placeGoogleMapsUrl && (
        <a href={pick.placeGoogleMapsUrl} target="_blank" rel="noreferrer" className="mt-3 inline-block font-semibold text-sambal-deep underline">
          View all reviews on Google Maps
        </a>
      )}
    </section>
  )
}
