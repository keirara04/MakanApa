import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { ApiError } from '../../api/client'
import { decideApi } from '../../api/decide'
import { distanceKm, nearbyApi, type NearbyPlace, type PlaceDetails } from '../../api/nearby'
import { useSession } from '../../auth/session'
import { errorMessage } from '../../lib/errors'
import type { Coordinates } from '../../lib/location'
import { directionsUrl } from '../../lib/maps'
import { resizeForUpload } from '../../lib/photos'
import { categoryLabel, priceLabel } from '../../lib/presentation'
import { getStored, setStored, useStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { Sheet } from '../../ui/Sheet'
import { HalalBadge, HalalSection } from '../decide/PickParts'
import type { SearchContext } from './SearchPanel'

/** One id per place per visit, reused on retry so "Eat here" is never recorded twice. */
const choiceIds = new Map<number, string>()

export function PlaceSheet({ place, search, user: me, onClose, onExclude, onBackToResults }: {
  place: NearbyPlace | null
  search: SearchContext | null
  user: Coordinates | null
  onClose: () => void
  onExclude: (id: number) => void
  onBackToResults: (() => void) | null
}) {
  const { data: details, error, isLoading } = useQuery({
    queryKey: ['place', place?.id],
    queryFn: ({ signal }) => nearbyApi.details(place!.id, signal),
    enabled: place !== null,
    staleTime: 10 * 60_000,
  })

  if (!place) return <Sheet open={false} onClose={onClose} title="">{null}</Sheet>

  const summary = search?.result
  const status = (summary?.openStatus ?? details?.openStatus ?? place.openStatus)
  const closesAt = summary?.closesAt ?? details?.closesAt
  const away = me ? distanceKm(me, place) : null
  const where = [away === null ? null : away < 1 ? `${Math.round((away * 1000) / 10) * 10} m away` : `${away.toFixed(1)} km away`, summary?.address].filter(Boolean)
  const category = summary?.category ?? categoryLabel(details?.foodCategory ?? null)
  const halal = details?.halal?.display ?? place.halal?.display

  return (
    <Sheet open onClose={onClose} title={place.name}>
      {onBackToResults && (
        <button type="button" onClick={onBackToResults} className="-mt-2 self-start text-sm font-semibold text-sambal-deep">
          ‹ Results for “{search?.query}”
        </button>
      )}

      <div className="-mt-2 flex items-start gap-3">
        <div className="flex-1 space-y-1">
          {status === 'open' && <p className="font-semibold text-pandan">{closesAt ? `Open, closes ${closesAt}` : 'Open'}</p>}
          {status === 'closed' && <p className="font-semibold text-sambal-deep">Closed</p>}
          {where.length > 0 && <p className="text-sm text-kicap-soft">{where.join(', ')}</p>}
          <p className="flex flex-wrap gap-x-3 text-sm">
            {place.rating !== null && <span className="font-semibold">★ {place.rating.toFixed(1)}</span>}
            {priceLabel(place.priceLevel) && <span>{priceLabel(place.priceLevel)}</span>}
            {category && <span>{category}</span>}
          </p>
          {halal && <HalalBadge halal={{ display: halal }} />}
        </div>
        <SaveHeart place={place} />
      </div>

      <div className="flex gap-2">
        <a
          href={directionsUrl({ ...place, placeGoogleMapsUrl: details?.placeGoogleMapsUrl }, getStored('mapsApp'))}
          target="_blank"
          rel="noreferrer"
          className="press flex min-h-12 flex-1 items-center justify-center rounded-full bg-sambal font-display text-lg font-semibold text-white"
        >
          Directions
        </a>
        <EatHere place={place} search={search} me={me} />
      </div>

      {(details?.shareUrl ?? summary?.shareUrl) && <Share place={place} url={(details?.shareUrl ?? summary?.shareUrl)!} detail={summary?.address ?? category} />}
      <AmbassadorPick place={place} />

      {isLoading && <p role="status" className="text-kicap-soft">Loading details…</p>}
      {error && <p role="alert">{errorMessage(error)}</p>}
      {details && <DetailBody details={details} />}

      <button
        type="button"
        onClick={() => {
          onExclude(place.id)
          onClose()
        }}
        className="min-h-11 self-start text-sm font-semibold text-kicap-soft underline"
      >
        Don’t suggest this place again
      </button>
    </Sheet>
  )
}

function SaveHeart({ place }: { place: NearbyPlace }) {
  const saved = (useStored('saved') ?? []).some((item) => item.id === place.id)
  return (
    <button
      type="button"
      aria-pressed={saved}
      aria-label={saved ? `Remove ${place.name} from saved` : `Save ${place.name}`}
      onClick={() => {
        const list = getStored('saved') ?? []
        if (saved) {
          setStored('saved', list.filter((item) => item.id !== place.id))
          void decideApi.unsave(place.id).catch(() => {})
        } else {
          setStored('saved', [{ id: place.id, name: place.name, latitude: place.latitude, longitude: place.longitude, savedAt: Date.now() }, ...list])
          void decideApi.save(place.id).catch(() => {})
        }
      }}
      className="press flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-kicap bg-surface"
    >
      <svg viewBox="0 0 24 24" className={`h-6 w-6 ${saved ? 'fill-sambal text-sambal' : 'fill-none'}`} stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
      </svg>
    </button>
  )
}

/** "Eat here": records an accepted pick of this place and adds it to Recent. */
function EatHere({ place, search, me }: { place: NearbyPlace; search: SearchContext | null; me: Coordinates | null }) {
  const [state, setState] = useState<'idle' | 'sending' | 'done' | 'failed'>('idle')

  async function eatHere() {
    if (state === 'sending' || state === 'done') return
    setState('sending')
    let clientChoiceId = choiceIds.get(place.id)
    if (!clientChoiceId) {
      clientChoiceId = crypto.randomUUID()
      choiceIds.set(place.id, clientChoiceId)
    }
    try {
      await nearbyApi.choose(place.id, {
        clientChoiceId,
        ...(me ?? {}),
        openedFrom: search ? 'search' : 'nearby',
        ...(search ? { search: { query: search.query, radiusKm: search.radiusKm, source: search.source } } : {}),
      })
      const recent = (getStored('recent') ?? []).filter((item) => item.id !== place.id)
      setStored('recent', [
        { id: place.id, name: place.name, latitude: place.latitude, longitude: place.longitude, foodCategory: search?.result.category ?? null, priceLevel: place.priceLevel, placeGoogleMapsUrl: null, at: Date.now(), source: 'nearby' as const },
        ...recent,
      ].slice(0, 5))
      setState('done')
    } catch {
      setState('failed')
    }
  }

  return (
    <button
      type="button"
      onClick={() => void eatHere()}
      aria-busy={state === 'sending' || undefined}
      title="Marks this as where you’re eating. It shows up in Recent."
      className="press min-h-12 flex-1 rounded-full bg-sambal/10 font-display text-lg font-semibold text-sambal-deep"
    >
      {state === 'done' ? 'Added to Recent' : state === 'failed' ? 'Try again' : state === 'sending' ? 'Saving…' : 'Eat here'}
    </button>
  )
}

function Share({ place, url, detail }: { place: NearbyPlace; url: string; detail: string | null | undefined }) {
  const text = `Want to eat here? ${place.name}${detail ? `, ${detail}` : ''}\n${url}`
  const record = () => void decideApi.shareEvent(place.id).catch(() => {})
  return (
    <div className="flex flex-wrap gap-2">
      <a href={`https://wa.me/?text=${encodeURIComponent(text)}`} target="_blank" rel="noreferrer" onClick={record} className="press inline-flex min-h-11 items-center rounded-full border-2 border-kicap bg-surface px-5 font-display font-semibold">
        Send to friends
      </a>
      {'share' in navigator && (
        <button
          type="button"
          onClick={() => {
            record()
            void navigator.share({ title: place.name, text, url }).catch(() => {})
          }}
          className="min-h-11 px-3 font-display font-semibold text-sambal-deep underline"
        >
          More ways to share
        </button>
      )}
    </div>
  )
}

/** Ambassadors add the place to their campus picks, with an optional note. */
function AmbassadorPick({ place }: { place: NearbyPlace }) {
  const { user } = useSession()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const { data } = useQuery({ queryKey: ['ambassador-picks'], queryFn: nearbyApi.ambassadorPicks, enabled: Boolean(user?.ambassadorOf) })
  if (!user?.ambassadorOf) return null

  const current = data?.picks.find((pick) => pick.restaurantId === place.id)
  const done = () => {
    void queryClient.invalidateQueries({ queryKey: ['ambassador-picks'] })
    setOpen(false)
  }

  return (
    <>
      <button
        type="button"
        onClick={() => {
          setNote(current?.note ?? '')
          setError(null)
          setOpen(true)
        }}
        className={`press min-h-11 rounded-full border-2 border-kunyit font-display font-semibold ${current ? 'bg-kunyit/25' : 'bg-surface'}`}
      >
        {current ? '★ In your picks' : '☆ Add to my picks'}
      </button>
      <Sheet open={open} onClose={() => setOpen(false)} title="Your pick">
        <p className="font-semibold">{place.name}</p>
        <label className="flex flex-col gap-1">
          <span className="sr-only">Note</span>
          <textarea value={note} onChange={(event) => setNote(event.target.value.slice(0, 140))} rows={3} placeholder="What should people order? (optional)" className="rounded-row border-2 border-hairline bg-cream p-3" />
          <span className="self-end text-xs text-kicap-soft">{note.length}/140</span>
        </label>
        {error && <p role="alert" className="text-sambal-deep">{error}</p>}
        <Button
          onClick={() =>
            nearbyApi
              .addAmbassadorPick(place.id, note.trim() || null)
              .then(done)
              .catch((caught) => setError(caught instanceof ApiError ? caught.message : 'Couldn’t save your pick. Try again.'))
          }
        >
          Save
        </Button>
        {current && (
          <Button variant="quiet" onClick={() => nearbyApi.removeAmbassadorPick(place.id).then(done).catch((caught) => setError(errorMessage(caught)))}>
            Remove from my picks
          </Button>
        )}
        <Button variant="quiet" onClick={() => setOpen(false)}>
          Cancel
        </Button>
      </Sheet>
    </>
  )
}

function DetailBody({ details }: { details: PlaceDetails }) {
  const photos = [...details.photos.map((photo) => photo.url), ...details.communityPhotos]
  const contact: [string, string][] = []
  if (details.phone) contact.push(['Call', `tel:${details.phone.replace(/[^\d+]/g, '')}`])
  if (details.instagramHandle) contact.push(['Instagram', `https://instagram.com/${details.instagramHandle.replace(/^@/, '')}`])
  if (details.tiktokHandle) contact.push(['TikTok', `https://www.tiktok.com/@${details.tiktokHandle.replace(/^@/, '')}`])
  if (details.websiteUrl) contact.push(['Website', details.websiteUrl])

  return (
    <>
      <HalalSection halal={details.halal} />

      {photos.length > 0 ? (
        <div className="flex snap-x snap-mandatory gap-2 overflow-x-auto [scrollbar-width:none]" aria-label="Photos">
          {photos.map((url, index) => (
            <img key={url} src={url} alt={index === 0 ? details.name : ''} loading="lazy" className="h-44 w-64 shrink-0 snap-start rounded-row bg-hairline object-cover" />
          ))}
        </div>
      ) : (
        <QuickAddPhoto placeId={details.id} />
      )}
      {details.communityPhotos.length > 0 && <p className="-mt-2 text-xs text-kicap-soft">Some photos shared by the MakanApa community</p>}

      {details.menuItems.length > 0 && (
        <Section title="Menu">
          <ul className="divide-y divide-hairline">
            {details.menuItems.map((item) => (
              <li key={`${item.name}-${item.price}`} className="flex justify-between gap-4 py-2">
                <span>{item.name}</span>
                {item.price !== null && <span className="font-semibold">RM{item.price.toFixed(2)}</span>}
              </li>
            ))}
          </ul>
        </Section>
      )}

      {details.reviews.length > 0 && (
        <Section title="Reviews from Google Maps">
          {details.reviews.slice(0, 2).map((review, index) => (
            <div key={index} className="mt-2">
              {review.rating !== null && <p className="text-kunyit" aria-label={`${review.rating} out of 5`}>{'★'.repeat(Math.round(review.rating))}</p>}
              {review.text && <p className="italic">“{review.text.length > 120 ? `${review.text.slice(0, 120)}…` : review.text}”</p>}
              <p className="text-sm text-kicap-soft">{review.authorName}</p>
            </div>
          ))}
        </Section>
      )}

      {contact.length > 0 && (
        <Section title="Contact">
          <div className="flex flex-wrap gap-2">
            {contact.map(([label, href]) => (
              <a key={label} href={href} target="_blank" rel="noreferrer" className="press inline-flex min-h-11 items-center rounded-full border-2 border-hairline bg-cream px-4 font-semibold">
                {label}
              </a>
            ))}
          </div>
        </Section>
      )}
    </>
  )
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section>
      <h3 className="font-display text-lg font-semibold">{title}</h3>
      {children}
    </section>
  )
}

/** No photos yet: signed-in people can add one (reviewed before it's public). */
function QuickAddPhoto({ placeId }: { placeId: number }) {
  const { user } = useSession()
  const navigate = useNavigate()
  const [state, setState] = useState<'idle' | 'uploading' | 'done' | 'failed' | 'gate'>('idle')
  const [message, setMessage] = useState<string | null>(null)

  if (state === 'gate') {
    return (
      <div className="rounded-row border-2 border-dashed border-hairline p-4">
        <p className="font-display font-semibold">Sign in to add photos</p>
        <p className="mt-1 text-sm text-kicap-soft">
          Picks, Nearby and saves work without an account. Adding places, posting and halal reports need one, so the community knows who’s contributing.
        </p>
        <div className="mt-3 flex gap-2">
          <Button className="max-w-xs" onClick={() => navigate('/sign-in')}>
            Sign in
          </Button>
          <Button variant="quiet" onClick={() => setState('idle')}>
            Not now
          </Button>
        </div>
      </div>
    )
  }

  return (
    <label className={`flex min-h-32 cursor-pointer flex-col items-center justify-center gap-1 rounded-row border-2 border-dashed border-hairline text-center ${state === 'uploading' ? 'opacity-60' : ''}`}>
      <span className="font-display font-semibold">
        {state === 'done' ? 'Thanks! Pending review' : state === 'failed' ? 'Couldn’t add. Tap to retry' : state === 'uploading' ? 'Uploading…' : 'Add a photo'}
      </span>
      {message && <span className="px-4 text-sm text-kicap-soft">{message}</span>}
      <input
        type="file"
        accept="image/*"
        className="sr-only"
        disabled={state === 'uploading' || state === 'done'}
        onClick={(event) => {
          if (user?.isGuest) {
            event.preventDefault()
            setState('gate')
          }
        }}
        onChange={async (event) => {
          const file = event.target.files?.[0]
          if (!file) return
          setState('uploading')
          setMessage(null)
          try {
            await nearbyApi.quickAddPhoto(placeId, await resizeForUpload(file))
            setState('done')
          } catch (caught) {
            setState('failed')
            if (caught instanceof ApiError && caught.code === 'terms_required') setMessage(caught.message)
          }
        }}
      />
    </label>
  )
}
