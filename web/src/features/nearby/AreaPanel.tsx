import { useState } from 'react'
import { centreOf, distanceKm, type NearbyPlace } from '../../api/nearby'
import { useStored } from '../../lib/storage'
import { Button } from '../../ui/Button'
import { useNearby } from './state'

type Sort = 'default' | 'rating' | 'price' | 'distance'
const SORTS: [Sort, string][] = [
  ['default', 'Default'],
  ['rating', 'Rating'],
  ['price', 'Price'],
  ['distance', 'Distance'],
]

/**
 * "Around here": the area summary and every place that fits the chips, with Pick for me.
 * The map sits beside it on wide screens, above it on phones (or on its own without a map key).
 */
export function AreaPanel({ onOpen, onPick, picking, inPanel = false }: { onOpen: (place: NearbyPlace) => void; onPick: () => void; picking: boolean; inPanel?: boolean }) {
  const { summary, filtered, places, loading, viewport } = useNearby()
  // The summary comes from the server; places hidden with "Don't suggest" leave its lists too.
  const excluded = useStored('excluded') ?? []
  const topRated = summary?.topRated.filter((place) => !excluded.includes(place.id)) ?? []
  const communityFinds = summary?.communityFinds.filter((place) => !excluded.includes(place.id)) ?? []
  const [sort, setSort] = useState<Sort>('default')

  const sorted = [...filtered]
  const centre = viewport ? centreOf(viewport) : null
  if (sort === 'rating') sorted.sort((a, b) => (b.rating ?? -1) - (a.rating ?? -1))
  if (sort === 'price') sorted.sort((a, b) => (a.priceLevel ?? 9) - (b.priceLevel ?? 9))
  if (sort === 'distance' && centre) sorted.sort((a, b) => distanceKm(centre, a) - distanceKm(centre, b))

  const pickButton = (
    <Button onClick={onPick} busy={picking} disabled={picking || places.length === 0}>
      {picking ? 'Thinking…' : 'Pick for me'}
    </Button>
  )

  if (!summary) {
    return (
      <section className="flex flex-col items-center gap-2 py-10 text-center" aria-live="polite">
        {loading ? <p role="status">Finding places to eat around here…</p> : <p className="text-kicap-soft">Places around you will show up here.</p>}
      </section>
    )
  }

  if (summary.placeCount === 0) {
    return (
      <section className="py-10 text-center">
        <h2 className="font-display text-2xl font-bold">Nothing around here yet</h2>
        <p className="mt-1 text-kicap-soft">Try moving the map or clearing a filter.</p>
      </section>
    )
  }

  return (
    <section className="flex flex-col gap-6" aria-labelledby="around-title">
      <div>
        {/* In the phone panel, its header already says this and holds Pick for me. */}
        <h2 id="around-title" className={inPanel ? 'sr-only' : 'font-display text-2xl font-bold'}>
          Around here
        </h2>
        <dl className={`${inPanel ? '' : 'mt-3'} grid grid-cols-3 gap-2 text-center`}>
          {[
            [summary.placeCount, 'places'],
            [summary.openNowCount, 'open now'],
            [summary.budgetFriendlyCount, 'under RM20'],
          ].map(([count, label]) => (
            <div key={label} className={`rounded-row px-2 py-3 ${inPanel ? 'bg-cream' : 'bg-surface'}`}>
              <dt className="sr-only">{label}</dt>
              <dd className="font-display text-2xl font-bold">{count}</dd>
              <dd aria-hidden="true" className="text-xs text-kicap-soft">
                {label}
              </dd>
            </div>
          ))}
        </dl>
        {summary.personalityTags.length > 0 && (
          <ul className="mt-3 flex flex-wrap gap-2">
            {summary.personalityTags.map((tag) => (
              <li key={tag.key} className="rounded-full bg-kunyit/25 px-3 py-1 text-sm font-semibold">
                {tag.label.replace(/_/g, ' ')}
              </li>
            ))}
          </ul>
        )}
      </div>

      {topRated.length > 0 && <PlaceList title="Top rated" places={topRated} onOpen={onOpen} numbered />}
      {communityFinds.length > 0 && <PlaceList title="Community finds" places={communityFinds} onOpen={onOpen} />}

      {!inPanel && pickButton}

      <div>
        <div className="flex items-center justify-between gap-2">
          <h2 className="font-display text-xl font-bold">All {filtered.length} places</h2>
        </div>
        <div className="mt-2 flex gap-2" role="group" aria-label="Sort">
          {SORTS.map(([key, label]) => (
            <button
              key={key}
              type="button"
              aria-pressed={sort === key}
              onClick={() => setSort(key)}
              className={`press min-h-9 rounded-full px-3 text-sm font-semibold ${sort === key ? 'bg-sambal-deep text-white' : 'bg-kicap/5'}`}
            >
              {label}
            </button>
          ))}
        </div>
        <ul className="mt-2 divide-y-2 divide-hairline">
          {sorted.map((place) => (
            <li key={place.id}>
              <button type="button" onClick={() => onOpen(place)} className="flex min-h-14 w-full items-center gap-3 py-2 text-left">
                <span className="flex-1">
                  <span className="block font-semibold">{place.name}</span>
                  <span className="text-xs text-kicap-soft">{place.openStatus === 'open' ? 'Open' : place.openStatus === 'closed' ? 'Closed' : ''}</span>
                </span>
                {place.rating !== null && <span className="font-display font-semibold text-kicap">★ {place.rating.toFixed(1)}</span>}
              </button>
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}

function PlaceList({ title, places, onOpen, numbered = false }: { title: string; places: NearbyPlace[]; onOpen: (place: NearbyPlace) => void; numbered?: boolean }) {
  return (
    <div>
      <h3 className="font-display text-base font-semibold text-kicap-soft">{title}</h3>
      <ul className="mt-1">
        {places.map((place, index) => (
          <li key={place.id}>
            <button type="button" onClick={() => onOpen(place)} className="flex min-h-11 w-full items-center gap-3 text-left">
              <span className="flex-1 font-semibold">
                {numbered && `${index + 1}. `}
                {place.name}
              </span>
              {place.rating !== null && <span className="text-sm font-semibold">★ {place.rating.toFixed(1)}</span>}
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
