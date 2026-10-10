import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { decideApi } from '../../api/decide'
import { nearbyApi, type SearchResult } from '../../api/nearby'
import { useSession } from '../../auth/session'
import { useLocation } from '../../lib/location'
import { directionsUrl } from '../../lib/maps'
import { priceLabel } from '../../lib/presentation'
import { getStored, setStored, useStored, type SavedPlace } from '../../lib/storage'
import { SubPage } from './SubPage'

/** Saved places live on this device; saves are also counted on the server for the community. */
export function SavedPlaces() {
  const saved = useStored('saved') ?? []
  const [text, setText] = useState('')
  const query = text.trim()

  return (
    <SubPage title="Saved">
      <input
        type="search"
        value={text}
        onChange={(event) => setText(event.target.value)}
        placeholder="Search places to save"
        aria-label="Search places to save"
        className="min-h-12 rounded-full border-2 border-hairline bg-surface px-5 text-lg placeholder:text-kicap-soft focus:border-kicap focus:outline-none"
      />

      {query.length >= 2 ? (
        <SaveSearch query={query} saved={saved} />
      ) : saved.length === 0 ? (
        <div className="py-8 text-center">
          <h2 className="font-display text-2xl font-bold">No saved places yet</h2>
          <p className="mt-1 text-kicap-soft">Tap the heart on a place in Nearby, or search above to save one.</p>
        </div>
      ) : (
        <>
          <ul className="divide-y-2 divide-hairline border-y-2 border-hairline">
            {saved.map((place) => (
              <li key={place.id} className="flex items-center gap-2">
                <a href={directionsUrl(place, getStored('mapsApp'))} target="_blank" rel="noreferrer" className="flex min-h-16 flex-1 flex-col justify-center py-3">
                  <span className="font-semibold">{place.name}</span>
                  <span className="text-sm text-kicap-soft">
                    {[place.rating != null ? `★ ${place.rating.toFixed(1)}` : null, priceLabel(place.priceLevel ?? null)].filter(Boolean).join(', ')}
                  </span>
                </a>
                <button type="button" onClick={() => toggle(place, true)} aria-label={`Remove ${place.name} from saved`} className="press h-11 w-11 text-kicap-soft">
                  ✕
                </button>
              </li>
            ))}
          </ul>
          {saved.length >= 2 ? (
            <Link to="/decide/saved" className="press flex min-h-14 items-center justify-center rounded-full bg-sambal font-display text-xl font-semibold text-white">
              Pick one for me
            </Link>
          ) : (
            <p className="text-sm text-kicap-soft">Save one more place and MakanApa can pick between them.</p>
          )}
        </>
      )}
    </SubPage>
  )
}

function toggle(place: SavedPlace, isSaved: boolean) {
  const list = getStored('saved') ?? []
  if (isSaved) {
    setStored('saved', list.filter((item) => item.id !== place.id))
    void decideApi.unsave(place.id).catch(() => {})
  } else {
    setStored('saved', [{ ...place, savedAt: Date.now() }, ...list])
    void decideApi.save(place.id).catch(() => {})
  }
}

function SaveSearch({ query, saved }: { query: string; saved: SavedPlace[] }) {
  const { user } = useSession()
  const location = useLocation()
  const [debounced, setDebounced] = useState(query)
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(query), 350)
    return () => clearTimeout(timer)
  }, [query])

  // Around you, or around your saved places when location is off.
  const origin =
    location.state.status === 'ready'
      ? location.state.coords
      : saved.length
        ? { latitude: saved.reduce((sum, p) => sum + p.latitude, 0) / saved.length, longitude: saved.reduce((sum, p) => sum + p.longitude, 0) / saved.length }
        : null

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['save-search', debounced, origin?.latitude.toFixed(2), origin?.longitude.toFixed(2)],
    queryFn: ({ signal }) => nearbyApi.search({ query: debounced, ...origin!, radiusKm: 25, halal: user?.halalPreference ?? false }, signal),
    enabled: origin !== null && debounced.length >= 2,
  })

  const local = saved.filter((place) => place.name.toLowerCase().includes(query.toLowerCase()))

  async function save(result: SearchResult) {
    setError(null)
    let place = result
    try {
      if (place.id === null && place.googlePlaceId) {
        setBusy(place.googlePlaceId)
        place = (await nearbyApi.resolve(place.googlePlaceId)).restaurant
      }
    } catch {
      setError('Couldn’t save that place. Try again.')
      return
    } finally {
      setBusy(null)
    }
    if (place.id === null) return
    const isSaved = saved.some((item) => item.id === place.id)
    toggle({ id: place.id, name: place.name, latitude: place.latitude, longitude: place.longitude, rating: place.rating, priceLevel: place.priceLevel, savedAt: Date.now() }, isSaved)
  }

  return (
    <div className="flex flex-col gap-4" aria-live="polite">
      {local.length > 0 && (
        <section>
          <h2 className="text-sm font-semibold text-kicap-soft">Your saved places</h2>
          <ul>
            {local.map((place) => (
              <li key={place.id} className="min-h-11 py-2 font-semibold">
                {place.name}
              </li>
            ))}
          </ul>
        </section>
      )}
      <section>
        <h2 className="text-sm font-semibold text-kicap-soft">Save a place</h2>
        {!origin && <p className="py-2">Turn on location to search places around you.</p>}
        {isLoading && origin && <p role="status" className="py-2">Searching…</p>}
        {isError && (
          <p className="py-2">
            Couldn’t search right now. Check your connection and try again.{' '}
            <button type="button" className="underline" onClick={() => void refetch()}>
              Try again
            </button>
          </p>
        )}
        {data && data.results.length === 0 && <p className="py-2">No places match “{debounced}” around you.</p>}
        <ul className="divide-y-2 divide-hairline">
          {data?.results.map((result) => {
            const isSaved = result.id !== null && saved.some((item) => item.id === result.id)
            return (
              <li key={result.id ?? result.googlePlaceId} className="flex items-center gap-3 py-3">
                <span className="flex-1">
                  <span className="block font-semibold">{result.name}</span>
                  <span className="text-sm text-kicap-soft">
                    {[result.category, result.distanceKm !== null ? `${result.distanceKm.toFixed(1)} km` : null, result.address].filter(Boolean).join(', ')}
                  </span>
                </span>
                <button
                  type="button"
                  aria-pressed={isSaved}
                  aria-label={isSaved ? `Remove ${result.name} from saved` : `Save ${result.name}`}
                  aria-busy={busy === result.googlePlaceId || undefined}
                  onClick={() => void save(result)}
                  className="press flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-kicap bg-surface"
                >
                  <svg viewBox="0 0 24 24" className={`h-5 w-5 ${isSaved ? 'fill-sambal text-sambal' : 'fill-none'}`} stroke="currentColor" strokeWidth="2" aria-hidden="true">
                    <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" />
                  </svg>
                </button>
              </li>
            )
          })}
        </ul>
        {data && (
          <Link to={`/community/add?q=${encodeURIComponent(debounced)}`} state={{ from: '/settings/saved' }} className="press mt-2 flex min-h-11 flex-col rounded-row border-2 border-dashed border-hairline px-4 py-3">
            <span className="font-semibold text-sambal-deep">Can’t find it? Add it as a new place</span>
            <span className="text-sm text-kicap-soft">We’ll check MakanApa and Google first. New places show up after a quick review.</span>
          </Link>
        )}
        {error && <p role="alert" className="text-sambal-deep">{error}</p>}
      </section>
    </div>
  )
}
